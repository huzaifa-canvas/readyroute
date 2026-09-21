import { log } from '../config.js'
import { reportPresence } from '../laravel.js'

/**
 * Tracks how many sockets each user has open.
 *
 * A driver with the app open on two devices, or reconnecting through a tunnel,
 * briefly holds more than one socket. Reporting offline on the first
 * disconnect would flicker their status on the dispatcher's board, so a user
 * is only offline once their last socket has gone.
 */
const connectionsByUser = new Map()

function addConnection(userId) {
  const count = (connectionsByUser.get(userId) || 0) + 1
  connectionsByUser.set(userId, count)

  return count
}

function removeConnection(userId) {
  const count = (connectionsByUser.get(userId) || 1) - 1

  if (count <= 0) {
    connectionsByUser.delete(userId)

    return 0
  }

  connectionsByUser.set(userId, count)

  return count
}

export function onlineUserIds() {
  return [...connectionsByUser.keys()]
}

export function registerConnectionHandlers(io, socket) {
  const { user, rooms } = socket.data

  rooms.forEach((room) => socket.join(room))

  const openSockets = addConnection(user.id)

  log('info', 'Client connected', {
    userId: user.id,
    role: user.role,
    rooms,
    openSockets,
  })

  // Only the first socket flips presence on.
  if (openSockets === 1 && user.role === 'driver') {
    reportPresence(user.id, true)
  }

  socket.emit('ready', { user, rooms })

  // Lets a viewer see who is typing without persisting anything.
  socket.on('message:typing', (payload = {}) => {
    const room = typeof payload.room === 'string' ? payload.room : null

    if (!room || !socket.rooms.has(room)) {
      return
    }

    socket.to(room).emit('typing', {
      user_id: user.id,
      name: user.name,
      is_typing: Boolean(payload.is_typing),
    })
  })

  // A client may ask to join a room, but only one it was cleared for at
  // handshake. Membership is never taken from what the client claims to be.
  socket.on('thread:join', (payload = {}, ack) => {
    const room = typeof payload.room === 'string' ? payload.room : null
    const allowed = room && rooms.includes(room)

    if (allowed) {
      socket.join(room)
    }

    if (typeof ack === 'function') {
      ack({ joined: Boolean(allowed) })
    }
  })

  socket.on('presence:ping', (_payload, ack) => {
    if (typeof ack === 'function') {
      ack({ ok: true, at: new Date().toISOString() })
    }
  })

  socket.on('disconnect', (reason) => {
    const remaining = removeConnection(user.id)

    log('info', 'Client disconnected', { userId: user.id, reason, remaining })

    if (remaining === 0 && user.role === 'driver') {
      reportPresence(user.id, false)
    }
  })
}
