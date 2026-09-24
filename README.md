# 24U s.r.o - Zadání: Full-stack Developer - vývoj webových aplikací se zaměřením na UX - praktická část

## Část 1 - Webová aplikace
Vytvořte webovou aplikaci pro evidenci knih. Aplikace se bude skládat z veřejné a administrační části.

### Veřejná část
* výpis knih (název, autor, rok vydání),
* detail knihy po kliknutí (např. anotace, hodnocení),
* možnost tisku seznamu knih.

### Administrace
* přihlášení,
* formulář pro přidání nové knihy (včetně validace),
* import knih z připraveného souboru `books.json`.

### Technické požadavky:

#### Backend
* PHP 8.x (čisté PHP nebo framework),
* SQL databáze (MySQL/MariaDB nebo SQLite),
* Docker + docker-compose,
* aplikace musí být spustitelná v prostředí Apache/PHP.

#### Frontend
* HTML,
* CSS,
* JavaScript,
* SASS (.scss).

#### Git
* GitHub nebo jiný veřejně dostupný Git repozitář.
* Předpokládáme několik commitů zachycujících průběh vývoje.

### Při hodnocení webové části bude kladen důraz zejména na:
* přehledné a intuitivní uživatelské rozhraní,
* uživatelskou přívětivost aplikace,
* použitelnost aplikace,
* dodržení zadání,
* funkčnost,
* čistotu a srozumitelnost kódu,
* jednoduchost zprovoznění aplikace.

---

## Část 2 - Databáze ve FileMakeru
Vytvořte databázi **Knihovna**.

V databázi vytvořte tabulku **Knihy** s následujícími poli:
* Název,
* Autor,
* Rok vydání,
* Hodnocení.

Vytvořte:
* rozvržení **Seznam knih**,
* rozvržení **Detail knihy**.

V rozvržení **Detail knihy** využijte vhodné objekty FileMakeru pro editaci jednotlivých polí. Do databáze vložte alespoň tři ukázkové knihy.

Obě rozvržení připravte tak, jak si myslíte, že bude pro uživatele vhodné a příjemné na používání.

### FileMaker bonus (dobrovolný)
Pokud si budete chtít vyzkoušet více, nebo nám ukázat, že zvládnete i složitjší část, můžete vyřešit jednu nebo více následujících úloh:
* vytvořit tabulku **Autoři** a propojit ji relací s tabulkou **Knihy**,
* na detailu autora zobrazit seznam jeho knih pomocí portálu,
* vytvořit skript pro založení nové knihy,
* vytvořit skript pro přechod mezi seznamem knih a detailem knihy,
* vytvořit tabulku **Žánry** a umožnit výběr žánru pomocí rozbalovacího seznamu,
* vytvořit tlačítko pro tisk seznamu knih.

### Při hodnocení FileMaker části bude kladen důraz zejména na:
* přehledné a intuitivní uživatelské rozhraní,
* uživatelskou přívětivost aplikace,
* použitelnost aplikace,
* dodržení zadání,
* funkčnost.