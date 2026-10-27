# Gemini chat failure diagnosis

This note explains the server-side chat request, the generic failure shown in the UI, the safe diagnostic fields written to Laravel logs, and how to use them on Render.

## Request flow

1. The browser submits a standard HTML `POST` form from `resources/views/chat/show.blade.php`. This is not a JavaScript `fetch()` or Axios request.
2. `routes/web.php` registers `POST /conversations/{conversation}/messages` as `messages.store`. It is behind the `web` and `auth` middleware and uses the `throttle:chat` rate limiter.
3. `SendMessageRequest` authorizes access to the conversation and validates a required string up to 4,000 characters.
4. `MessageController::store()` persists the user message, updates the conversation timestamp, and writes sentiment metadata.
5. Crisis-detection messages receive the application's local safety response and do not call Gemini. For other messages, the controller checks that the user explicitly consented to Gemini processing.
6. If consent is present, `GeminiChatService::reply()` reads the server configuration, builds conversation history, and sends a synchronous Laravel HTTP POST to Google's `generateContent` endpoint. The timeout is 30 seconds.
7. On a successful Gemini response, the controller records successful usage, saves the assistant message, and redirects back to the conversation.
8. On a caught `RuntimeException` or `ConnectionException` from the Gemini call, the user message remains saved, the usage result is recorded as failed, a warning is logged, and the browser is redirected back with a generic chat error.

There is no separate API route for this flow, and no frontend promise to await. The user's message is saved before the external Gemini request; the two saves are not in a single transaction. Therefore a failed AI call does not roll back the user message.

## Configuration names

The application reads only these Gemini environment variables:

- `GEMINI_API_KEY` → `config('services.gemini.api_key')`
- `GEMINI_MODEL` → `config('services.gemini.model')`

`GOOGLE_API_KEY` and `OPENAI_API_KEY` are not used by this integration. The Privacy page's “API key is configured” message checks only that a non-empty key value exists; it does not validate the key against Google or confirm access to the selected model.

The model value must be a model identifier accepted by Google's `v1beta/models/{model}:generateContent` endpoint. Do not include an additional `models/` prefix in the value.

## Diagnostic logging and privacy

Gemini failures can originate from:

- missing `GEMINI_API_KEY` or `GEMINI_MODEL`;
- a non-success HTTP response, such as a credential/model permission error, quota/rate limit, or provider outage;
- connection failures or the 30-second timeout;
- a successful HTTP response with no text candidate, including a safety-blocked response.

The application logs:

- `Gemini configuration is incomplete.` with `missing_setting`;
- `Gemini API request failed.` with HTTP status, provider error code/status, and model;
- `Gemini API returned no text candidate.` with model, candidate finish reason, and prompt block reason;
- `Gemini response failed.` with conversation ID, exception class, and a redacted exception message.

These log entries intentionally exclude the API key, chat prompt, and generated response. Do not add request bodies, authorization headers, API keys, or message text to logs.

The browser deliberately receives a generic message rather than Google's raw response. Provider error text can contain implementation details and should not be shown directly to users.

## Render investigation steps

1. Confirm the Render deployment contains the commit with the diagnostic logging. In the Render service's **Events** or **Deploys**, check the deployed commit.
2. In Render **Environment**, verify `GEMINI_API_KEY` and `GEMINI_MODEL` are set on the web service. Do not paste either value into logs, tickets, screenshots, or chat.
3. In the app, visit **Privacy** and enable the explicit Gemini-processing consent. The chat is intentionally not sent to Gemini without consent.
4. Send a short, ordinary, non-crisis test message. Note its time and conversation ID.
5. Open Render **Logs** for the same time and find `Gemini response failed.`. Inspect the preceding Gemini-specific entry:
   - `missing_setting`: add the named variable to the Render web service and restart/redeploy.
   - HTTP `401` or `403`: check that the server-side key is active and allowed to use the Gemini API/model.
   - HTTP `404`: check the exact model identifier and whether that model supports `generateContent`.
   - HTTP `429`: check quota, billing/limits, and request rate in the Google project.
   - HTTP `5xx`: retry later and check provider availability.
   - connection exception: check outbound connectivity and whether requests exceed the 30-second timeout.
   - no text candidate with a block reason: the provider returned a safety-blocked response; do not retry the same disallowed content.
6. Share only the HTTP status, provider code/status, exception class, and non-sensitive model identifier when requesting help. Redact hostnames, user identifiers, request content, keys, and all database credentials.

If Render shows only `Gemini response failed.` with no Gemini-specific entry, verify that the latest deployment is running. The diagnostic details are emitted by `GeminiChatService`; older deployed code may log only the generic exception class.

## Code locations

- `routes/web.php` — authenticated message POST route.
- `app/Http/Requests/SendMessageRequest.php` — conversation authorization and input validation.
- `app/Http/Controllers/MessageController.php` — user-message save, consent/crisis branches, Gemini catch, usage record, assistant-message save.
- `app/Services/AI/GeminiChatService.php` — Gemini configuration, request, timeout, provider status, and response parsing.
- `config/services.php` — maps `GEMINI_API_KEY` and `GEMINI_MODEL`.
- `resources/views/chat/show.blade.php` — standard HTML form and displayed errors.
- `resources/views/profile/privacy.blade.php` — user consent and presence-only key status.
