# AI Message Router

Proof of Concept mikroserwisu, który przyjmuje zgłoszenia od użytkowników przez
API, interpretuje ich treść za pomocą lokalnie uruchomionego modelu językowego
(Ollama), i automatycznie przekazuje je e-mailem do odpowiedniego działu firmy
— korzystając z mechanizmu tool/function calling (Agent AI sam decyduje, kiedy
i z jakimi parametrami wywołać narzędzie wysyłki maila).

## Szybki start

```bash
git clone https://github.com/akonopka/ai-message-router.git
cd ai-message-router
docker compose up -d
```

Pierwsze uruchomienie ściąga model `qwen2.5:3b` (ok. 2GB, może to
potrwać kilka minut w zależności od połączenia). Pobieranie jest realizowane
przez tymczasowy kontener `ollama-pull`. Aby sprawdzić, czy kontener zakończył 
już działanie można wywołać:
```bash
docker compose ps -a ollama-pull
```
Status `Exited (0)` oznacza sukces. 

`docker compose logs -f ollama-pull`
pokazuje status postępu pobierania (może wymagać przerwania przez Ctrl+C). 

Warto również sprawdzić bezpośrednio, czy model został poprawnie pobrany:

```bash
docker exec ai-message-router-ollama ollama list
```

Po pobraniu modelu środowisko jest gotowe:

- **Dokumentacja Swagger, umożliwiająca manualne testowanie API z przeglądarki** (bez potrzeby użycia curla): http://localhost:3005/api/v1/docs
- **MailHog** (przechwycone maile): http://localhost:8025

## Przykładowe zapytanie (CLI)

```bash
curl -X POST http://localhost:3005/api/v1/messages \
  -H "Content-Type: application/json" \
  -d '{
    "email": "jan.nowak@example.com",
    "message": "Nie działa mi komputer, proszę o pomoc."
  }'
```

Odpowiedź:
```json
{
  "response": "Wysłano zgłoszenie do działu Informatyki."
}
```

Zgłoszenie trafi jako e-mail do odpowiedniego działu (widoczny w MailHog pod
http://localhost:8025), z nagłówkiem `Reply-To` ustawionym na adres nadawcy
z requestu.

Opcjonalnie można podać własny temat maila:
```json
{
  "email": "jan.nowak@example.com",
  "message": "Chciałbym zgłosić urlop na jutro.",
  "subject": "Wniosek urlopowy"
}
```

## Dostępne adresy docelowe

- `human-resources@example.com`
- `help-desk@example.com`
- `it@example.com`
- `kadry@example.com`
- `other@example.com` — fallback dla zgłoszeń, których nie da się jednoznacznie
  zaklasyfikować

## Architektura i decyzje projektowe

**Stack**: PHP 8.4 + `symfony/skeleton` (minimalny szkielet Symfony, bez
zbędnych komponentów, których ten PoC nie potrzebuje) +
`symfony/ai-bundle` z bridge'em `symfony/ai-ollama-platform`.

**Serwer HTTP**: wbudowany serwer PHP (`php -S`) w tym jednym kontenerze, 
bez nginx albo Apache jako reverse proxy przed PHP-FPM jak powinno to wyglądać produkcyjnie.

**Ollama**: oficjalny obraz `ollama/ollama`, używany bezpośrednio bez własnego builda. 
Automatyczne pobranie modelu przy starcie realizuje osobny, tymczasowu serwis
`ollama-pull`, uruchamiany po tym jak `ollama` przejdzie healthcheck
(`depends_on: condition: service_healthy`) — bez tego `ollama pull` mógłby
zostać wywołany zanim serwer faktycznie wstanie.

**Rozdzielenie odpowiedzialności model / kod**: model AI odpowiada wyłącznie
za interpretację treści zgłoszenia i wybór działu (`departmentEmail`) — to
jedyny parametr, który faktycznie wymaga jego rozumowania. Adres nadawcy
(do nagłówka `Reply-To`) i opcjonalny temat maila są odczytywane bezpośrednio
z requestu HTTP wewnątrz narzędzia (tool), nie przepuszczane przez model — to
świadoma decyzja: dane, które są już znane i zaufane, nie powinny przechodzić
przez niedeterministyczny model tylko po to, żeby zostały "przepisane".

**Walidacja na granicy systemu**: request HTTP (`email`, `message`) jest
walidowany przez Symfony Validator (`#[MapRequestPayload]` + atrybuty
`Assert\*`) zanim trafi do Agenta — błędne dane odrzucane są od razu (HTTP
422), bez marnowania zasobów na wywołanie modelu. Wewnątrz narzędzia
wysyłkowego adres działu wybrany przez model jest dodatkowo weryfikowany
względem białej listy dozwolonych adresów (model to LLM — może się pomylić;
niepoprawna wartość jest zamieniana na fallback `other@example.com`).

**Deterministyczny fallback wywołania narzędzia**: ręcznie zweryfikowano
(wielokrotne, identyczne requesty przez curl), że dla całkowicie niejasnych/pustych
treści (np. placeholder "string") lokalny model (`qwen2.5:3b`) mimo jednoznacznej instrukcji 
w prompcie **nie zawsze** wywołuje narzędzie — w części przypadków odpowiada tekstem zamiast 
wykonać akcję. Kontroler śledzi to przez współdzielony (przez DI) `ToolInvocationTracker`
i jeśli narzędzie faktycznie się nie wykonało, sam bezpośrednio wywołuje je
z `other@example.com` — gwarantując wysyłkę niezależnie od tego, czy model
poprawnie zastosował się do instrukcji. To świadomy, jawny mechanizm
zabezpieczający — dokumentowany tutaj i w kodzie (`MessageRouterController`, `ToolInvocationTracker`).

**Dokumentacja API**: `nelmio/api-doc-bundle`, wystawiona pod `/api/v1/docs`
(interfejs Swagger UI, z możliwością testowania requestów bezpośrednio z
przeglądarki), surowy JSON specyfikacji OpenAPI dostępny osobno pod
`/api/v1/docs.json`.

## Wymagane kontenery

- **php** — API (PHP 8.4, wbudowany serwer)
- **ollama** / **ollama-pull** — silnik LLM + jednorazowy pull modelu
- **mailhog** — przechwytywanie wysyłanych maili (panel web + SMTP)
