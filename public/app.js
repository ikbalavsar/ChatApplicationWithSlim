const messagesContainer = document.getElementById('messages');
const sendMessageForm = document.getElementById('send-message-form');
const usernameInput = document.getElementById('username');
const messageInput = document.getElementById('message');
let lastMessageId = 0;

sendMessageForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const username = usernameInput.value.trim();
    const message = messageInput.value.trim();

    if (username === '' || message === '') {
        return;
    }

    const response = await fetch('http://localhost:8080/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            username,
            message,
        }),
    });

    if (response.ok) {
        messageInput.value = '';
        await fetchMessages();
    }
});

async function fetchMessages() {
    const response = await fetch(`http://localhost:8080/messages/${lastMessageId}`);
    if (!response.ok) {
        return;
    }

    const data = await response.json();
    for (const message of data.messages) {
        const messageElement = document.createElement('div');
        messageElement.classList.add('border-b', 'border-gray-300', 'py-2');
        messageElement.innerHTML = `<strong>${message.username}</strong>: ${message.message}`;
        messagesContainer.appendChild(messageElement);
        lastMessageId = message.id;
    }

    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

fetchMessages();
setInterval(fetchMessages, 3000);
