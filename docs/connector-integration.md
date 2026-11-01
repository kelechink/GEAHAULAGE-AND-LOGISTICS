# GPS tracker integration guide

GEA's platform accepts truck positions from GPS tracker companies through a signed webhook. This page is for the tracker company's developers.

## Endpoint

```
POST https://geahaulageandlogistics.com/api/connectors/webhook/webhook
Content-Type: application/json
X-GEA-Signature: sha256=<signature>
```

GEA staff enable the webhook and create the secret in **Settings → Integrations**, then share the secret with you securely.

## Signature

`<signature>` is the lowercase hex HMAC-SHA256 of the **raw request body**, using the shared secret as the key.

```bash
BODY='{"positions":[{"device_id":"864123045678901","lat":6.5244,"lng":3.3792,"speed":54,"recorded_at":"2026-11-02T14:15:00+01:00"}]}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')
curl -X POST https://geahaulageandlogistics.com/api/connectors/webhook/webhook \
  -H "Content-Type: application/json" -H "X-GEA-Signature: sha256=$SIG" -d "$BODY"
```

Requests with a missing or wrong signature get `401`.

## Body

Send one position, or several in a `positions` array.

| Field | Type | Required | Notes |
|---|---|---|---|
| `device_id` | string | yes | Must match the truck's "GPS tracker device ID" in GEA's system |
| `lat` | number | yes | Decimal degrees |
| `lng` | number | yes | Decimal degrees |
| `speed` | number | no | km/h |
| `recorded_at` | string | no | ISO 8601 with time zone. Defaults to the time GEA receives it |

## Response

```json
{"accepted": 1, "ignored": 0}
```

`ignored` counts positions for devices that aren't mapped to a truck, or trucks with no active trip. Those are dropped, not stored.

## Limits

Up to 120 requests a minute. Batch positions if you send more often than every 30 seconds. Positions outside West Africa, or at 0,0, are rejected as implausible.

## Traccar

If GEA runs its own Traccar server instead, no webhook is needed: staff add the server URL and an API token in **Settings → Integrations**, and the platform polls it every minute. Each tracker's Traccar "unique ID" goes in the truck's "GPS tracker device ID".
