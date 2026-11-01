// Find the chat form and its controls. This script is safe to load on pages
// that do not contain a chat form.
const chatForm = document.querySelector('#chat-form');
const chatMessages = document.querySelector('#chat-messages');
const messageInput = document.querySelector('#chat-input');
const sendButton = document.querySelector('#send-button');
const chatError = document.querySelector('#chat-error');

if (chatForm && chatMessages && messageInput && sendButton && chatError) {
    // Build message elements with textContent so user and AI text is not
    // interpreted as HTML.
    const appendMessage = (role, label, content) => {
        const article = document.createElement('article');
        article.className = `message ${role}`;

        const heading = document.createElement('strong');
        heading.textContent = label;

        const body = document.createElement('div');
        body.textContent = content;

        article.append(heading, body);
        chatMessages.append(article);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    };

    // Display request failures in the chat area, not only in the console.
    const showError = (message) => {
        chatError.textContent = message;
        chatError.hidden = false;
        chatError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    // Submit messages asynchronously to the Laravel route specified by the
    // form action. Laravel continues to handle authorization, persistence,
    // consent checks, and the Gemini API call on the server.
    chatForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const content = messageInput.value.trim();

        if (!content || sendButton.disabled) {
            return;
        }

        const csrfToken = chatForm.querySelector('input[name="_token"]')?.value;

        if (!csrfToken) {
            showError('We could not verify your session. Refresh the page and try again.');
            return;
        }

        chatError.hidden = true;
        chatError.textContent = '';
        appendMessage('user', 'You', content);
        messageInput.value = '';
        sendButton.disabled = true;
        messageInput.disabled = true;

        const typingIndicator = document.createElement('p');
        typingIndicator.className = 'muted small';
        typingIndicator.setAttribute('role', 'status');
        typingIndicator.setAttribute('aria-live', 'polite');
        typingIndicator.textContent = 'Gemini is typing...';
        chatMessages.append(typingIndicator);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        try {
            const response = await fetch(chatForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ content }),
                credentials: 'same-origin',
            });

            let payload;

            try {
                payload = await response.json();
            } catch {
                throw new Error('The server returned an unreadable response. Please refresh the page and try again.');
            }

            if (!payload || typeof payload !== 'object') {
                throw new Error('The server returned an invalid response. Please refresh the page and try again.');
            }

            if (!response.ok || payload.success !== true || typeof payload.reply !== 'string') {
                const errors = payload.errors && typeof payload.errors === 'object'
                    ? Object.values(payload.errors)
                    : [];
                const validationError = errors
                    .flat()
                    .find((error) => typeof error === 'string');

                throw new Error(
                    payload.error
                    || validationError
                    || 'We could not get a reply right now. Please try again later.',
                );
            }

            appendMessage('assistant', 'MindCare assistant', payload.reply);
        } catch (error) {
            showError(error instanceof Error
                ? error.message
                : 'We could not get a reply right now. Please try again later.');
        } finally {
            typingIndicator.remove();
            sendButton.disabled = false;
            messageInput.disabled = false;
            messageInput.focus();
        }
    });
}
