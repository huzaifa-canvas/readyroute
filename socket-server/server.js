import express from 'express'
import { createServer } from 'node:http'
import { Server } from 'socket.io'

import { config, log } from './src/config.js'
import { verifyToken } from './src/laravel.js'
import { onlineUserIds, registerConnectionHandlers } from './src/handlers/connection.js'

const app = express()
app.use(express.json({ limit: '256kb' }))

const httpServer = createServer(app)

const io = new Server(httpServer, {
  cors: {
    // Mobile clients send no Origin header, so this only constrains browsers.
    origin: config.corsOrigins.length > 0 ? config.corsOrigins : true,
    credentials: true,
  },
  // Long enough to ride out a driver passing through a tunnel.
  pingTimeout: 30000,
  pingInterval: 25000,
})

/*
 * Authentication. The client presents the same Sanctum token it uses for the
 * REST API; Laravel decides who that is and which rooms they may join. A
 * socket that fails this never reaches a handler.
 */
io.use(async (socket, next) => {
  const token =
    socket.handshake.auth?.token ||
    socket.handshake.headers?.authorization?.replace(/^Bearer\s+/i, '')

  if (!token) {
    return next(new Error('unauthorized'))
  }

  const identity = await verifyToken(token)

  if (!identity?.user) {
    return next(new Error('unauthorized'))
  }

  socket.data.user = identity.user
  socket.data.rooms = identity.rooms || []

  return next()
})

io.on('connection', (socket) => registerConnectionHandlers(io, socket))

/*
 * The only way into this server from Laravel. Guarded by the shared secret,
 * and expected to be reachable on localhost only.
 */
function requireSecret(req, res, next) {
  const provided = req.get('X-Socket-Secret') || ''

  if (provided !== config.secret) {
    return res.status(401).json({ status: false, message: 'Invalid socket credentials.' })
  }

  return next()
}

app.post('/emit', requireSecret, (req, res) => {
  const { room, event, payload } = req.body || {}

  if (typeof room !== 'string' || typeof event !== 'string') {
    return res.status(422).json({ status: false, message: 'room and event are required.' })
  }

  io.to(room).emit(event, payload ?? {})

  log('debug', 'Emitted', { room, event })

  return res.json({ status: true })
})

app.get('/health', (req, res) => {
  res.json({
    status: true,
    uptime_seconds: Math.round(process.uptime()),
    sockets: io.engine.clientsCount,
    online_users: onlineUserIds().length,
  })
})

httpServer.listen(config.port, () => {
  log('info', 'Socket server listening', { port: config.port, laravel: config.laravelUrl })
})

// Let PM2 and the dev runner stop the process cleanly.
for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => {
    log('info', 'Shutting down', { signal })
    io.close(() => httpServer.close(() => process.exit(0)))
  })
}
