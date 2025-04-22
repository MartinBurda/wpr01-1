document.addEventListener("DOMContentLoaded", function () {
    const repairId = document.querySelector("input[name='repairId']").value;
    const chatMessages = document.getElementById("chat-messages");
    const chatInput = document.getElementById("chat-input");
    const chatSend = document.getElementById("chat-send");

    if (!repairId) return;

    function loadMessages() {
        fetch(`?do=getMessages&repairId=${repairId}`)
            .then(response => {
                if (!response.ok) throw new Error("Failed to fetch messages");
                return response.json();
            })
            .then(messages => {
                chatMessages.innerHTML = messages.map(msg => `
                    <div style="padding: 5px; border-bottom: 1px solid #ddd;">
                        <small style="font-size: 12px; font-weight: bold; color: ${msg.sender_id === CURRENT_USER_ID ? '#007bff' : '#28a745'}">
                            ${msg.sender_name}
                        </small>
                        <div>${msg.message}</div>
                        <small style="float: right; color: gray; font-size: 10px;">${msg.created_at}</small>
                    </div>
                `).join("");
                chatMessages.scrollTop = chatMessages.scrollHeight;
            })
            .catch(error => console.error("Error fetching messages:", error));
    }

    function sendMessage() {
        const message = chatInput.value.trim();
        if (message === "") return;

        fetch(`?do=sendMessage`, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ repairId: repairId, message: message, senderId: CURRENT_USER_ID })
        })
        .then(response => response.json())
        .then(response => {
            if (response.status === 'success') {
                chatInput.value = "";
                loadMessages();
            } else {
                console.error("Error sending message:", response.message);
            }
        })
        .catch(error => console.error("Error:", error));
    }

    chatSend.addEventListener("click", sendMessage);

    chatInput.addEventListener("keydown", function (event) {
        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    setInterval(loadMessages, 5000);
    loadMessages();
});
