const chatUrl = "http://10.103.8.116/67notatnik/chat.php";
const apiUrl  = "http://10.103.8.116/67notatnik/api.php";

async function apiLoadMessages(partnerId) {
  const token = localStorage.getItem("token");
  const res = await fetch(chatUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${token}`
    },
    body: JSON.stringify({ read: true, partnerId: Number(partnerId) })
  });
  if (!res.ok) {
    console.error("Błąd pobierania wiadomości:", res.status, await res.text());
    return [];
  }
  return res.json();
}

async function apiSendMessage(receiverId, content) {
  const token = localStorage.getItem("token");
  const res = await fetch(chatUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${token}`
    },
    body: JSON.stringify({ receiverId: Number(receiverId), content: String(content).trim() })
  });
  if (!res.ok) {
    console.error("Błąd wysyłania wiadomości:", res.status, await res.text());
    return null;
  }
  return res.json();
}

async function apiLoadUsers() {
  const token = localStorage.getItem("token");
  const res = await fetch(`${apiUrl}/users`, {   // <-- poprawione
    headers: { "Authorization": `Bearer ${token}` }
  });
  if (!res.ok) {
    console.error("Błąd pobierania użytkowników:", res.status, await res.text());
    return [];
  }
  return res.json();
}
