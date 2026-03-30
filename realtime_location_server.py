import asyncio
import base64
import hashlib
import hmac
import json
import os
import time
import urllib.parse
import urllib.request
from typing import Dict, List

from fastapi import FastAPI, Query, WebSocket, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI()
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["GET", "POST", "OPTIONS"],
    allow_headers=["*"],
)

PHP_BASE_URL = os.environ.get('PHP_API_BASE_URL', 'http://localhost/Bii_localFinder')
WS_AUTH_SECRET = os.environ.get('WS_AUTH_SECRET', 'BiiLocalFinderLiveLocationSecret_v1')
THROTTLE_SECONDS = float(os.environ.get('LIVE_LOCATION_THROTTLE_SECONDS', '2.0'))

rooms: Dict[str, List[Dict]] = {}
last_sent: Dict[str, float] = {}


def validate_token(token: str) -> Dict:
    try:
        payload_b64, signature = token.split('.', 1)
    except ValueError:
        return {}

    expected = hmac.new(WS_AUTH_SECRET.encode('utf-8'), payload_b64.encode('utf-8'), hashlib.sha256).hexdigest()
    if not hmac.compare_digest(expected, signature):
        return {}

    try:
        payload_json = base64.b64decode(payload_b64).decode('utf-8')
        payload = json.loads(payload_json)
    except Exception:
        return {}

    if not isinstance(payload, dict):
        return {}

    if payload.get('exp', 0) < int(time.time()):
        return {}

    if not payload.get('uid') or not payload.get('room'):
        return {}

    return payload


def save_location_to_php(data: Dict) -> None:
    try:
        url = f"{PHP_BASE_URL}/api/save_location.php"
        encoded = urllib.parse.urlencode(data).encode('utf-8')
        request = urllib.request.Request(url, data=encoded, method='POST')
        request.add_header('Content-Type', 'application/x-www-form-urlencoded')
        with urllib.request.urlopen(request, timeout=5) as response:
            response.read()
    except Exception as exc:
        print('save_location_to_php error', exc)


def broadcast_to_room(room: str, message: Dict, sender: WebSocket) -> None:
    if room not in rooms:
        return
    text = json.dumps(message)
    for connection in list(rooms[room]):
        ws = connection['websocket']
        if ws is sender:
            continue
        try:
            asyncio.create_task(ws.send_text(text))
        except Exception:
            pass


@app.websocket('/ws/live_location')
async def live_location_endpoint(websocket: WebSocket, conversation_id: str = Query(...), token: str = Query(...)):
    await websocket.accept()

    payload = validate_token(token)
    if not payload or payload.get('room') != conversation_id:
        await websocket.close(code=4001)
        return

    user_id = int(payload['uid'])
    room = conversation_id
    if room not in rooms:
        rooms[room] = []
    rooms[room].append({'websocket': websocket, 'user_id': user_id})

    try:
        while True:
            text = await websocket.receive_text()
            try:
                data = json.loads(text)
            except json.JSONDecodeError:
                continue

            if data.get('type') != 'send_location':
                continue

            action = data.get('action', 'update')
            latitude = float(data.get('latitude', 0) or 0)
            longitude = float(data.get('longitude', 0) or 0)
            if action == 'update':
                key = f"{room}:{user_id}"
                now = time.monotonic()
                if last_sent.get(key, 0) + THROTTLE_SECONDS > now:
                    continue
                last_sent[key] = now

                payload = {
                    'type': 'receive_location',
                    'payload': {
                        'conversation_id': room,
                        'user_id': user_id,
                        'latitude': latitude,
                        'longitude': longitude,
                        'updated_at': time.strftime('%Y-%m-%d %H:%M:%S'),
                        'action': 'update',
                    },
                }
                broadcast_to_room(room, payload, websocket)
                asyncio.create_task(save_location_to_php({
                    'action': 'update',
                    'conversation_id': room,
                    'user_id': user_id,
                    'latitude': latitude,
                    'longitude': longitude,
                    'save_history': '0',
                }))
            elif action == 'stop':
                payload = {
                    'type': 'user_stopped_sharing',
                    'payload': {
                        'conversation_id': room,
                        'user_id': user_id,
                    },
                }
                broadcast_to_room(room, payload, websocket)
                asyncio.create_task(save_location_to_php({
                    'action': 'stop',
                    'conversation_id': room,
                    'user_id': user_id,
                }))
    except WebSocketDisconnect:
        pass
    finally:
        room_connections = rooms.get(room, [])
        rooms[room] = [conn for conn in room_connections if conn['websocket'] is not websocket]
        if not rooms[room]:
            rooms.pop(room, None)


if __name__ == '__main__':
    import uvicorn
    uvicorn.run('realtime_location_server:app', host='0.0.0.0', port=8765, reload=False)
