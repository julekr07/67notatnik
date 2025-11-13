// ======= ELEMENTY DOM =======
const form = document.getElementById("form");
const input = document.getElementById("input");
const messagesDiv = document.getElementById("messages");
const nameInput = document.getElementById("nameInput");
const setNameBtn = document.getElementById("setNameBtn");
const usersList = document.getElementById("usersList");
const connectedStatus = document.getElementById("connectedStatus");

let username = "Anonim";
let users = [username]; // lokalna lista użytkowników

// ======= INICJALIZACJA =======
connectedStatus.textContent = "tryb lokalny (offline)";
connectedStatus.style.color = "#ffa64d";

renderUsers();

// ======= ZMIANA NAZWY =======
setNameBtn.addEventListener("click", () => {
  const newName = nameInput.value.trim();
  if (!newName) return alert("Podaj nazwę użytkownika!");

  username = newName;

  // Zastąp w liście
  users[0] = username;
  renderUsers();

  nameInput.value = "";
});

// ======= WYSYŁANIE WIADOMOŚCI =======
form.addEventListener("submit", (e) => {
  e.preventDefault();
  const text = input.value.trim();
  if (!text) return;

  const message = {
    user: username,
    text: text,
    time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
  };

  appendMessage(message, true);
  input.value = "";
});

// ======= FUNKCJE POMOCNICZE =======
function appendMessage(msg, isOwn = false) {
  const div = document.createElement("div");
  div.classList.add("message");
  div.classList.add(isOwn ? "user" : "other");

  const header = document.createElement("div");
  header.innerHTML = `<strong>${msg.user}</strong> <small style="opacity:0.7;">${msg.time}</small>`;
  header.style.marginBottom = "3px";

  const text = document.createElement("div");
  text.textContent = msg.text;

  div.appendChild(header);
  div.appendChild(text);
  messagesDiv.appendChild(div);

  // przewiń na dół
  messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function renderUsers() {
  usersList.innerHTML = "";
  users.forEach((user) => {
    const li = document.createElement("li");
    li.textContent = user;
    usersList.appendChild(li);
  });
}
