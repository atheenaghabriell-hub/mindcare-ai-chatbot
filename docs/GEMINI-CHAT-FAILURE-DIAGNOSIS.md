# Gemini chat failure diagnosis

This note explains the server-side chat request, the generic failure shown in the UI, the safe diagnostic fields written to Laravel logs, and how to use them on Render.

## Request flow

1. `resources/js/script.js` intercepts the chat form in `resources/views/chat/show.blade.php` and sends a same-origin `fetch()` POST with JSON and the Laravel CSRF token.
2. `routes/web.php` registers `POST /conversations/{conversation}/messages` as `messages.store`. It is behind the `web` and `auth` middleware and uses the `throttle:chat` rate limiter.
3. `SendMessageRequest` authorizes access to the conversation and validates a required string up to 4,000 characters.
4. `MessageController::store()` persists the user message, updates the conversation timestamp, and writes sentiment metadata.
5. Crisis-detection messages receive the application's local safety response and do not call Gemini. For other messages, the controller checks that the user explicitly consented to Gemini processing.
6. If consent is present, `GeminiChatService::reply()` reads the server configuration, builds conversation history, and sends a synchronous Laravel HTTP POST to Google's `generateContent` endpoint. Each attempt has a 20-second timeout.
7. On a successful Gemini response, the controller records successful usage, saves the assistant message, and returns it as JSON for the browser to append to the chat.
8. The Gemini service retries one time after a connection failure, HTTP 408/429, or HTTP 5xx response. Other HTTP errors are not retried. After the final failure, the user message remains saved, the usage result is recorded as failed, a warning is logged, and the browser displays a friendly error.

There is no separate PHP `chat_handler.php` endpoint; the existing authenticated Laravel route serves the JSON request. The user's message is saved before the external Gemini request; the two saves are not in a single transaction. Therefore a failed AI call does not roll back the user message.

## Configuration names

The application reads only these Gemini environment variables:

- `GEMINI_API_KEY` → `config('services.gemini.api_key')`
- `GEMINI_MODEL` → `config('services.gemini.model')`

`GOOGLE_API_KEY` and `OPENAI_API_KEY` are not used by this integration. The Privacy page's “API key is configured” message checks only that a non-empty key value exists; it does not validate the key against Google or confirm access to the selected model.

The Privacy page checks that both values are present, but does not validate the key with Google. The model value must be a model identifier accepted by Google's `v1beta/models/{model}:generateContent` endpoint. Do not include an additional `models/` prefix in the value.

For `gemini-3.8-flash`, the request omits `generationConfig.temperature`; other configured models retain the existing `0.4` value. A 400 response can indicate an invalid request configuration, so verify the provider status/code in the Render logs if the issue persists.

## Diagnostic logging and privacy

Gemini failures can originate from:

- missing `GEMINI_API_KEY` or `GEMINI_MODEL`;
- a non-success HTTP response, such as a credential/model permission error, quota/rate limit, or provider outage;
- connection failures or the 30-second timeout;
- a successful HTTP response with no text candidate, including a safety-blocked response.

The application logs:

- `Gemini configuration is incomplete.` with `missing_setting` and `diagnostic_id`;
- `Gemini API request failed.` with HTTP status, provider error code/status, model, and `diagnostic_id`;
- `Gemini API returned no text candidate.` with model, candidate finish reason, prompt block reason, and `diagnostic_id`;
- `Gemini response failed.` with conversation ID, exception class, stable `failure_code`, and `diagnostic_id`.

The browser shows a safe, categorized explanation and the same diagnostic reference. Use that reference to locate all matching log entries and identify which failure path occurred. For example, `authentication_failed` points to server credentials/API access, `model_not_found` points to `GEMINI_MODEL` or model availability, `quota_or_rate_limit` points to provider quota, and `connection_failed` points to network connectivity. The privacy checkbox records user consent only; it cannot verify credentials, model access, quota, or provider availability.

These log entries intentionally exclude the API key, chat prompt, generated response, and raw provider response body. Do not add request bodies, authorization headers, API keys, or message text to logs.

The browser receives a categorized, safe explanation and diagnostic reference rather than Google's raw response. Provider error text can contain implementation details and should not be shown directly to users.

## Render investigation steps

1. Confirm the Render deployment contains the commit with the diagnostic logging. In the Render service's **Events** or **Deploys**, check the deployed commit.
2. In Render **Environment**, verify `GEMINI_API_KEY` and `GEMINI_MODEL` are set on the web service. Do not paste either value into logs, tickets, screenshots, or chat.
3. In the app, visit **Privacy** and enable the explicit Gemini-processing consent. The chat is intentionally not sent to Gemini without consent.
4. Send a short, ordinary, non-crisis test message. Note its time and conversation ID.
5. If the chat displays an error, copy its diagnostic reference and search Render **Logs** for that exact `diagnostic_id`. Inspect the `failure_code` and preceding Gemini-specific entry:
   - `missing_api_key` or `missing_model`: set the named environment variable on the Render web service and redeploy.
   - `authentication_failed`: check that the server-side key is active and allowed to use the Gemini API/model.
   - `model_not_found`: check the exact model identifier and whether that model supports `generateContent`.
   - `quota_or_rate_limit`: check quota, billing/limits, and request rate in the Google project.
   - `provider_unavailable` or `provider_timeout`: retry later and check provider availability.
   - `connection_failed`: check outbound connectivity and whether requests can reach Google's API.
   - `empty_response`: inspect the logged finish/block reason; a safety block should not be retried with the same disallowed content.
6. If needed, use the HTTP status and provider code/status from `Gemini API request failed.` to confirm the category:
   - HTTP `401` or `403`: check that the server-side key is active and allowed to use the Gemini API/model.
   - HTTP `404`: check the exact model identifier and whether that model supports `generateContent`.
   - HTTP `429`: check quota, billing/limits, and request rate in the Google project.
   - HTTP `5xx`: retry later and check provider availability.
   - connection exception: check outbound connectivity and whether requests exceed the 20-second per-attempt timeout.
   - no text candidate with a block reason: the provider returned a safety-blocked response; do not retry the same disallowed content.
7. Share only the diagnostic reference, failure code, HTTP status, provider code/status, exception class, and non-sensitive model identifier when requesting help. Redact hostnames, user identifiers, request content, keys, and all database credentials.

If Render shows only `Gemini response failed.` with no Gemini-specific entry, verify that the latest deployment is running. Older deployed code may not include a diagnostic reference or categorized failure code.

## Code locations

- `routes/web.php` — authenticated message POST route.
- `app/Http/Requests/SendMessageRequest.php` — conversation authorization and input validation.
- `app/Http/Controllers/MessageController.php` — user-message save, consent/crisis branches, Gemini catch, usage record, assistant-message save.
- `app/Services/AI/GeminiChatService.php` — Gemini configuration, request, timeout, provider status, and response parsing.
- `config/services.php` — maps `GEMINI_API_KEY` and `GEMINI_MODEL`.
- `resources/views/chat/show.blade.php` — standard HTML form and displayed errors.
- `resources/views/profile/privacy.blade.php` — user consent and presence-only Gemini configuration status.
