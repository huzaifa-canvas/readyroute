# ReadyRoute socket server

Realtime delivery for dispatch chat, notifications and driver presence. It is a
separate Node process with its own dependencies, deliberately kept out of the
Vuexy `package.json` at the project root.

## What it does and does not do

It **delivers** events. It does not write to the database and is not a source of
truth for anything.

Messages are posted to the Laravel API over HTTP, validated, scoped to the
correct company and written to the database. Only then is an event pushed here
for delivery. That means a socket server that is down or restarting delays
realtime delivery and nothing else: the message is already saved, and clients
fall back to fetching the thread over REST.

## Setup

```bash
cd socket-server
npm install
cp .env.example .env
```

Set `SOCKET_SECRET` in `socket-server/.env` to the same value as `SOCKET_SECRET`
in the Laravel `.env`. The server refuses to boot without it, because these
endpoints hand out user identity.

## Running

Locally, `composer run dev` from the project root starts it alongside the PHP
server, queue worker, logs and Vite.

To run it alone:

```bash
npm run dev     # with reload
npm start       # plain
```

In production, `pm2 start ecosystem.config.cjs`. That file also starts the
Laravel queue worker, which socket delivery depends on.

## Authentication

Clients connect with the same Sanctum token they use for the REST API:

```js
io('wss://readyroute.example.com', { auth: { token: '<sanctum token>' } })
```

This server cannot read a Sanctum token. It calls
`POST /api/internal/socket/verify` on Laravel, which returns the user and the
rooms they are allowed to join. Room membership is never taken from anything the
client claims.

## Rooms

| Room | Carries |
| --- | --- |
| `user.{id}` | Notifications and trip assignments for one person |
| `thread.{dispatcherId}.{driverId}` | One chat thread, its typing and read events |
| `dispatcher.{id}` | Company-wide broadcasts |

## Events

Client to server: `thread:join`, `message:typing`, `presence:ping`.

Server to client: `ready`, `message:new`, `message:read`, `typing`,
`notification:new`, `trip:assigned`.

## Presence

Connecting and disconnecting reports to Laravel, which is what makes the
dispatcher's driver count real rather than assumed. A user is only marked
offline once their **last** socket closes, so a second device or a brief
reconnect does not flicker their status.

## Deployment note

Mobile clients, iOS especially, refuse plaintext WebSocket connections. Put this
behind nginx so clients reach it over `wss://`, and keep port 3001 closed to the
outside world.
