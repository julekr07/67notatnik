// ======= ELEMENTY DOM =======
const form = document.getElementById("form");
const input = document.getElementById("input");
const messagesDiv = document.getElementById("messages");
const nameInput = document.getElementById("nameInput");
const setNameBtn = document.getElementById("setNameBtn");
const usersList = document.getElementById("usersList");
const connectedStatus = document.getElementById("connectedStatus");

// ======= ZMIENNE GLOBALNE =======
let username = "Anonim2706";
let users = [];  // każdy użytkownik będzie obiektem { id, name }

// ======= INICJALIZACJA =======
connectedStatus.style.color = "#ffa64d";
renderUsers();





// ======= DODANIE UŻYTKOWNIKA =======
setNameBtn.addEventListener("click", () => {
    const name = nameInput.value.trim();
    if (name === "") return;

    const newUser = {
        name: name
    };

    username = name;
    users.push(newUser);

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


// ======= WIADOMOŚCI =======
function appendMessage(msg, isOwn = false) {
  const div = document.createElement("div");
  div.classList.add("message", isOwn ? "user" : "other");

  const header = document.createElement("div");
  header.innerHTML = `<strong>${msg.user}</strong> <small style="opacity:0.7;">${msg.time}</small>`;
  header.style.marginBottom = "3px";

  const text = document.createElement("div");
  text.textContent = msg.text;

  div.appendChild(header);
  div.appendChild(text);
  messagesDiv.appendChild(div);

  messagesDiv.scrollTop = messagesDiv.scrollHeight;
}


// ======= RENDEROWANIE UŻYTKOWNIKÓW =======
function renderUsers() {
  usersList.innerHTML = "";

  users.forEach((user) => {
    const li = document.createElement("li");
    li.classList.add("list-group-item");

    // Ikony + ID + nazwa
    li.innerHTML = `
      <span style="color:#32CD32; font-size: 14px;">●</span> 
      <strong>${user.name}</strong>
    `;

    usersList.appendChild(li);
  });
}
