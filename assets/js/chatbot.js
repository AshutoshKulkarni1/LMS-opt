// ================================
// AI CHATBOT
// ================================

document.addEventListener("DOMContentLoaded", function () {

    const toggle = document.getElementById("chatbotToggle");
    const windowBox = document.getElementById("chatbotWindow");
    const closeBtn = document.getElementById("chatbotClose");

    const input = document.getElementById("chatbotInput");
    const sendBtn = document.getElementById("chatbotSend");
    const messages = document.getElementById("chatbotMessages");

    if (!toggle || !windowBox) {
        return;
    }

    // Open / close chatbot
    toggle.addEventListener("click", function () {
        windowBox.classList.toggle("open");

        if (windowBox.classList.contains("open")) {
            input.focus();
        }
    });

    closeBtn.addEventListener("click", function () {
        windowBox.classList.remove("open");
    });

    // Add message to chat
    function addMessage(text, type) {
        const message = document.createElement("div");

        message.className = "chatbot-message " + type;

        // textContent prevents HTML from being interpreted
        // This is important for preventing XSS.
        message.textContent = text;

        messages.appendChild(message);

        messages.scrollTop = messages.scrollHeight;

        return message;
    }

    // Send message
    async function sendMessage() {

        const text = input.value.trim();

        if (!text) {
            return;
        }

        addMessage(text, "user");

        input.value = "";
        sendBtn.disabled = true;

        // Display thinking indicator while waiting for AI response
        const thinkingIndicator = addMessage("Thinking...", "bot thinking");

        try {

            const response = await fetch("/api/chat.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    message: text
                })
            });

            const data = await response.json();

            // Remove thinking indicator
            if (thinkingIndicator && thinkingIndicator.parentNode) {
                thinkingIndicator.parentNode.removeChild(thinkingIndicator);
            }

            let botReply = "";
            if (data && data.message) {
                botReply = data.message;
            } else if (data && data.python_response && data.python_response.data && data.python_response.data.message) {
                botReply = data.python_response.data.message;
            } else if (data && data.error) {
                botReply = data.error;
            } else {
                botReply = "Sorry, I could not generate a response. Please try again.";
            }

            // Strip any remaining thinking tags if present
            botReply = botReply.replace(/<think>[\s\S]*?<\/think>/gi, '').trim();

            addMessage(botReply, "bot");

        } catch (error) {

            console.error(error);

            if (thinkingIndicator && thinkingIndicator.parentNode) {
                thinkingIndicator.parentNode.removeChild(thinkingIndicator);
            }

            addMessage(
                "Something went wrong while contacting the server.",
                "bot"
            );

        }

        sendBtn.disabled = false;
        input.focus();
    }

    sendBtn.addEventListener("click", sendMessage);

    // Press Enter to send
    input.addEventListener("keydown", function (event) {

        if (event.key === "Enter") {
            event.preventDefault();
            sendMessage();
        }

    });

    // Quick action buttons
    document.querySelectorAll(".chatbot-action").forEach(function (button) {

        button.addEventListener("click", function () {

            const message = button.dataset.message;

            if (!message) {
                return;
            }

            input.value = message;
            sendMessage();

        });

    });

});