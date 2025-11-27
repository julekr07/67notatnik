# 67notatnik

Dzień dobry panie Piotrze!!<br>
***<span style="color: red; font-size: 30px;">Witamy w grze😈😈😈</span>***

# 📘 Dokumentacja API – `school_api`

## 🔑 Autoryzacja

- API korzysta z **JWT**.
- Jedyny endpoint niewymagający tokena to `/auth`.
- Wszystkie pozostałe endpointy wymagają nagłówka:

Authorization: Bearer <TOKEN>


---

## 🌐 Endpointy

### 1. `POST /auth`
**Opis:** Logowanie użytkownika i wygenerowanie tokena JWT.

**Body (JSON):**
```json
{
"login": "jan",
"password": "haslo123"
}

Odpowiedź (200):

{
  "token": "eyJhbGciOiJIUzI1NiIsInR...",
  "userid": 1
}

```

**Błędy**:

```txt
400 – Missing login or password
401 – Invalid credentials
```
### `2. POST /users`
Opis: Pobiera listę wszystkich użytkowników (id i login).
```
Nagłówki:
Authorization: Bearer <TOKEN>

Body: brak
```
Odpowiedź (200):

```json
[
  { "id": 1, "login": "jan" },
  { "id": 2, "login": "anna" }
]
```
### 3. `POST /notes`

Opis: Obsługa prywatnych notatek użytkownika.
```
Nagłówki:
Authorization: Bearer <TOKEN>

a) Pobranie notatek
Body (JSON):

{ "read": true }
```
Odpowiedź (200):
```json
[
  { "id": 1, "userId": 1, "content": "Moja notatka" },
  { "id": 2, "userId": 1, "content": "Druga notatka" }
]
```
```
b) Dodanie notatki

Body (JSON):
{ "content": "Nowa notatka" }
```
Odpowiedź (201):
```json
{
    "success": true,
    "id": 3 
}
```

Błędy:
```
400 – Missing content / Invalid payload
401 – Missing token / Invalid token
403 – Brak uprawnień
404 – Unknown endpoint
405 – Method not allowed
```
#  📝 Uwagi

Wszystkie endpointy (poza /auth) wymagają tokena JWT.

Token ważny jest przez 1 godzinę (exp w payload).


Obsługa CORS jest włączona (Access-Control-Allow-Origin: *).
