import { config, log } from './config.js'

/**
 * The socket server deliberately cannot read a Sanctum token itself. It asks
 * Laravel, so there is exactly one place that decides who a token belongs to
 * and which rooms they may join.
 */
export async function verifyToken(token) {
  try {
    const response = await fetch(`${config.laravelUrl}/api/internal/socket/verify`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Socket-Secret': config.secret,
      },
      body: JSON.stringify({ token }),
      signal: AbortSignal.timeout(5000),
    })

    if (!response.ok) {
      return null
    }

    const body = await response.json()

    return body?.data ?? null
  } catch (error) {
    log('error', 'Token verification failed', { error: error.message })

    return null
  }
}

/**
 * Report a driver connecting or disconnecting. Failures are logged and
 * ignored: presence going stale is far less bad than dropping the socket.
 */
export async function reportPresence(userId, online) {
  try {
    await fetch(`${config.laravelUrl}/api/internal/socket/presence`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Socket-Secret': config.secret,
      },
      body: JSON.stringify({ user_id: userId, online }),
      signal: AbortSignal.timeout(5000),
    })
  } catch (error) {
    log('warn', 'Presence report failed', { userId, online, error: error.message })
  }
}
