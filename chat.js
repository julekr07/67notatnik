const chatUrl = "http://10.103.8.116/67notatnik/chat.php";
const apiUrl  = "http://10.103.8.116/67notatnik/api.php";

// pobieranie wszystkich wiadomości
async function apiLoadMessages() {
  const token = localStorage.getItem("token");
  const res = await fetch(chatUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${token}`
    },
    body: JSON.stringify({ read: true })
  });
  if (!res.ok) {
    console.error("Błąd pobierania wiadomości:", res.status, await res.text());
    return [];
  }
  return res.json();
}

// wysyłanie wiadomości
async function apiSendMessage(content) {
  const token = localStorage.getItem("token");
  if (!token) {
    console.error("Brak tokenu – musisz się zalogować!");
    return null;
  }
  const res = await fetch(chatUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${token}`
    },
    body: JSON.stringify({ content: String(content).trim() })
  });
  const text = await res.text();
  console.log("Odpowiedź serwera:", text);
  if (!res.ok) {
    console.error("Błąd wysyłania wiadomości:", res.status, text);
    return null;
  }
  return JSON.parse(text);
}

// pobieranie użytkowników
async function apiLoadUsers() {
  const token = localStorage.getItem("token");
  const res = await fetch(`${apiUrl}/users`, {
    headers: { "Authorization": `Bearer ${token}` }
  });
  if (!res.ok) {
    console.error("Błąd pobierania użytkowników:", res.status, await res.text());
    return [];
  }
  return res.json();
}
