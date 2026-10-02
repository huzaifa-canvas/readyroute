/*
 * A throwaway client for checking a deployed socket server end to end.
 *
 *   node test-client.mjs <socket-url> <sanctum-token>
 *
 * It connects the way the driver app does, prints which rooms the server let
 * it into, and then sits there printing every event it receives. That last
 * part is the point: it turns "is the socket working?" into something you can
 * watch, by sending a message from the dispatcher panel and seeing it land.
 *
 * Needs socket.io-client, which is not a dependency of the server itself:
 *
 *   npm install --no-save socket.io-client
 */
import { io } from 'socket.io-client'

const [url, token] = process.argv.slice(2)

if (!url || !token) {
  console.error('Usage: node test-client.mjs <socket-url> <sanctum-token>')
  process.exit(1)
}

const stamp = () => new Date().toLocaleTimeString()

console.log(`Connecting to ${url} ...`)

const socket = io(url, {
  auth: { token },
  // Report a failure rather than retrying quietly for ever, so a wrong token
  // or a blocked port shows up instead of looking like a hang.
  reconnectionAttempts: 3,
  timeout: 8000,
  transports: ['websocket', 'polling'],
})

socket.on('connect', () => {
  console.log(`[${stamp()}] connected   socket id ${socket.id}, transport ${socket.io.engine.transport.name}`)
})

socket.on('ready', (data) => {
  console.log(`[${stamp()}] ready       signed in as ${data.user?.name} (${data.user?.role}, id ${data.user?.id})`)
  console.log(`[${stamp()}] rooms       ${JSON.stringify(data.rooms)}`)
  console.log('')
  console.log('Listening. Send a message from the dispatcher panel to this driver,')
  console.log('or assign them a trip, and it should appear below. Ctrl+C to stop.')
  console.log('')
})

// Everything the server can push, so nothing arrives unnoticed.
for (const event of ['message:new', 'message:read', 'typing', 'notification:new', 'trip:assigned', 'trip:cancelled', 'incident:acknowledged', 'incident:resolved']) {
  socket.on(event, (payload) => {
    console.log(`[${stamp()}] ${event.padEnd(22)} ${JSON.stringify(payload)}`)
  })
}

socket.on('connect_error', (error) => {
  console.error(`[${stamp()}] connect_error: ${error.message}`)

  if (error.message === 'unauthorized') {
    console.error('   The token was rejected. Check it is a live Sanctum token and that')
    console.error('   LARAVEL_URL in socket-server/.env points at a reachable Laravel.')
  }
})

socket.on('disconnect', (reason) => {
  console.log(`[${stamp()}] disconnected: ${reason}`)
})

process.on('SIGINT', () => {
  socket.close()
  process.exit(0)
})
