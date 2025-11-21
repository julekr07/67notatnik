const apiUrl = "http://10.103.8.116/67notatnik/api.php";

// pobieranie notatek
async function apiLoadNotes() {
  const token = localStorage.getItem("token");
  const res = await fetch(`${apiUrl}?endpoint=notes`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${token}`
    },
    body: JSON.stringify({ read: true })
  });
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch {
    console.error("Serwer zwrócił HTML:", text);
    return [];
  }
}

// dodawanie notatki
async function apiAddNote(title, text) {
  const token = localStorage.getItem("token");
  const payload = { title, text };
  const res = await fetch(`${apiUrl}?endpoint=notes`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${token}`
    },
    body: JSON.stringify({ content: JSON.stringify(payload) })
  });
  const textRes = await res.text();
  try {
    return JSON.parse(textRes);
  } catch {
    console.error("Serwer zwrócił HTML:", textRes);
    return null;
  }
}
