// ======= ELEMENTY DOM =======
const form = document.getElementById("form");
const input = document.getElementById("input");
const messagesDiv = document.getElementById("messages");
const usersList = document.getElementById("usersList");
const connectedStatus = document.getElementById("connectedStatus");
const userIdSelect = document.getElementById("userIdSelect");

// ======= ZMIENNE GLOBALNE =======
let username = "Anonim";
let users = [];  // każdy użytkownik będzie obiektem { id, login }

// ======= INICJALIZACJA =======
connectedStatus.style.color = "#ffa64d";
loadUsers();

// ======= POBIERANIE LISTY UŻYTKOWNIKÓW =======
async function loadUsers() {
  try {
    const res = await fetch("users.php");
    const data = await res.json();
    users = data;

    // wypełnij select ID
    userIdSelect.innerHTML = '<option value="">-- wybierz --</option>';
    users.forEach(u => {
      const opt = document.createElement("option");
      opt.value = u.id;
      opt.textContent = `ID ${u.id} (${u.login})`;
      userIdSelect.appendChild(opt);
    });

    renderUsers();
  } catch (err) {
    console.error("Błąd pobierania użytkowników:", err);
  }
}

// ======= WYBÓR UŻYTKOWNIKA PO ID =======
userIdSelect.addEventListener("change", async () => {
  const id = userIdSelect.value;
  if (!id) return;

  try {
    const res = await fetch(`user.php?id=${id}`);
    const user = await res.json();

    if (user && user.login) {
      username = user.login;   // ustaw login jako nazwę w czacie
      console.log("Wybrano użytkownika:", username);
    } else {
      alert("Nie znaleziono użytkownika");
    }
  } catch (err) {
    console.error("Błąd pobierania użytkownika:", err);
  }
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
    li.innerHTML = `<span style="color:#32CD32; font-size: 14px;">●</span> <strong>${user.login}</strong>`;
    usersList.appendChild(li);
  });
}
