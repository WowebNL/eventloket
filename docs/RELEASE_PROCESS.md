# Release Process

Dit document beschrijft het proces voor het releasen van Eventloket, volgens Semantic Versioning (SemVer) en een gestructureerde Ontwikkel → Test → Productie straat.

## Semantic Versioning (SemVer)

Wij volgen [Semantic Versioning 2.0.0](https://semver.org/lang/nl/) voor versienummering:

**Format: `MAJOR.MINOR.PATCH`** (bijv. `v1.2.3`)

### Versie-incrementatie

- **MAJOR** (1.0.0): Niet-compatibele API-wijzigingen of grote features
- **MINOR** (0.1.0): Nieuwe functionaliteit, achterwaarts compatibel
- **PATCH** (0.0.1): Bug fixes en patches

**Voorbeeld:**
- `v0.1.0` → `v0.2.0`: nieuwe feature toevoegd
- `v0.1.0` → `v1.0.0`: grote breaking changes
- `v0.1.0` → `v0.1.1`: bug fix

## Workflow: Trunk based development

We hanteren trunk based development: `main` is de trunk, feature- en hotfix-branches zijn kortlevend en worden snel terug naar `main` gemerged. Voor actief ondersteunde versies houden we **`release/*` branches** bij waarop we gericht backport-commits kunnen toepassen en releasen.

Dit project volgt een **eenvoudig lineair workflow** met **automatische release management** via Release Drafter:

1. **Development** → Feature branches van `main` (label PRs met changelog labels)
2. **Testing & QA** → Op `main` branch, gemergde PR's worden uitgerold naar de testomgeving
3. **Automated Release Draft** → Release Drafter genereert automatisch release notes
4. **Release** → Tag op `main` (trunk) of op de relevante `release/*` branch bij backports
5. **Deploy** → Naar productie via release tag

### Branch Strategie

```
main (stabiel, getest)
├── release/1.2 (actieve versie met backports)
├── feature/user-authentication (label: changelog: feature)
├── bugfix/login-issue (label: changelog: bug)
└── tagged releases (v0.1.0, v0.2.0, etc.)
```

Voor onderhoud van oudere versies:
- Nieuwe ontwikkeling gebeurt op `main` (trunk)
- Backports voor ondersteunde versies gaan naar de bijbehorende `release/*` branch
- Release tags voor die versies worden vanaf de relevante `release/*` branch aangemaakt


## Development Workflow

### Nieuwe Feature / Fix

```bash
# Update main
git checkout main
git pull origin main

# Maak feature branch
git checkout -b feature/beschrijving-van-feature

# Werk eraan, commit regelmatig
git add .
git commit -m "description"
git push origin feature/beschrijving-van-feature

# Open Pull Request naar main
# - Code review
# - Tests moeten groen zijn
# - Label de PR met een changelog label (zie tabel hieronder)
# - Merge naar main
```

### Changelog Labels

Label je Pull Requests met één van deze labels zodat Release Drafter weet hoe de versie te verhogen:

| Label | Versie Impact | Voorbeeld |
|-------|---------------|-----------|
| `changelog: breaking` | **MAJOR** | API wijziging, verwijderde functionaliteit |
| `changelog: feature` | **MINOR** | Nieuwe feature toevoegd |
| `changelog: bug` | **PATCH** | Bug fix |
| `changelog: refactor` | **PATCH** | Code refactoring (geen functionaliteit veranderd) |
| `changelog: docs` | **PATCH** | Documentatie updates |
| `changelog: dependencies` | **PATCH** | Dependency updates |
| `skip-changelog` | Geen | PR niet in changelog opnemen |

**Tips:**
- Release Drafter labelt automatisch via branch naam (`feature/...` → feature label, `fix/...` → bug label)
- Je kan handmatig aanpassingen maken
- Zorg dat elke PR minimaal één changelog label heeft

### Tests en QA op Main

Na merge naar `main`:

1. CI/CD pipeline runt automatisch tests
2. Code is beschikbaar op test/staging server
3. QA team test de feature
4. Bugs? → Maak bugfix branch en merge terug
5. Alles stabiel en getest? → Release maken

## Release Proces met Automated Release Drafter

### Wat is Release Drafter?

**Release Drafter** is een GitHub Action die automatisch release notes genereert op basis van je Pull Requests en labels. Dit gebeurt continu en je kan het als "draft" release zien.

**Configuratie:** [.github/release-drafter.yml](./.github/release-drafter.yml)

### Hoe het werkt

1. **Automatic Draft**: Elke keer dat een PR wordt gemerged naar `main`, wordt een release draft automatisch bijgewerkt
2. **Version Auto-Calculation**: Bepaalt automatisch of het een MAJOR/MINOR/PATCH release is op basis van PR labels
3. **Changelog Generation**: Genereert mooie, georganiseerde release notes met categorieën:
   - 💥 Breaking changes
   - ✨ New features
   - 🐛 Bug Fixes
   - 📝 Documentation
   - ♻️ Refactor
   - ⬆️ Dependency Updates

Release Drafter draait op `main` (trunk); voor `release/*` branches stel je release notes en versies handmatig samen.

### Release Stappen

#### Stap 1: Check de Draft Release

```bash
# Ga naar GitHub
# Actions → Releases → Draft Release
```

Je ziet automatisch een draft release met:
- Voorgestelde versie (v0.2.0, v1.0.0, etc.)
- Automatisch gegenereerde changelog
- Link naar alle changes

#### Stap 2: Review & Aanpassingen

In GitHub:
- Controleer de gegenereerde release notes
- Edit titel, beschrijving, changelog indien nodig
- Verwijder entries die je niet wil
- Voeg extra info toe

#### Stap 3: Publish Release

```bash
# In GitHub UI:
# Draft Release → "Publish Release"
```

Dit doet automatisch:
- Creates git tag (bijv. `v0.2.0`)
- Publiceert de release op GitHub
- Triggert deployment naar productie (via CI/CD)

#### Stap 4: Deploy naar Productie

```bash
# Via CI/CD (aangrijpen bij release tag):
# - Tests draaien
# - Build artefacten
# - Deploy naar productie
```

### Voorbeeld Release Flow

```
PR #42: Feature - User dashboard
└─ Label: changelog: feature
└─ Merge naar main
   └─ Release Drafter update: v0.2.0 draft
      └─ Review in GitHub
         └─ Publish Release
            └─ Git tag: v0.2.0
               └─ CI/CD deploy triggered
```

### Versie Auto-Berekening

Release Drafter berekent automatisch de volgende versie vanaf `main` op basis van labels:

| PR Label | Impact |
|----------|--------|
| `changelog: breaking` | MAJOR (0.1.0 → 1.0.0) |
| `changelog: feature` | MINOR (0.1.0 → 0.2.0) |
| Overige (bug, docs, etc.) | PATCH (0.1.0 → 0.1.1) |

**Voorbeeld:**
- v0.1.0 + feature PR + bug PR = v0.2.0 (MINOR wint van PATCH)
- v0.2.0 + breaking change PR = v1.0.0 (MAJOR)

### Handmatige Release (zonder Draft)

Mocht je toch handmatig een release willen maken (bijv. vanaf `main` of een `release/*` branch voor backports):

```bash
# Kies de juiste basis (main = trunk, of release/1.2 voor backport)
git checkout main
git pull origin main

# Tag op de gekozen branch
git tag -a v0.2.0 -m "Release v0.2.0"
git push origin v0.2.0

# GitHub zal dit ook als release tonen
```

## Hotfixes (Kritieke Bugs in Productie)

Voor urgent bugs in productie:

```bash
# Maak hotfix branch van main (laatste versie) of van een relevante release branch (backport)
git checkout main
git checkout -b hotfix/critical-bug

# Fix en test
git commit -m "Fix critical bug"
git push origin hotfix/critical-bug

# Merge terug naar main (trunk)
git checkout main
git merge --no-ff hotfix/critical-bug
git push origin main

# (Optioneel) Cherry-pick naar release branch voor backport
git checkout release/1.2
git cherry-pick <commit_hash_van_hotfix>
git push origin release/1.2

# Tag en release op de branch die je zojuist hebt geüpdatet
git tag -a v0.1.1 -m "Hotfix v0.1.1"
git push origin v0.1.1

# Deploy naar productie
# Cleanup
git branch -d hotfix/critical-bug
```

## Automatische Backports met backport.yml

Voor geautomatiseerde backports naar `release/*` branches gebruiken we **backport.yml**, een GitHub Action die automatisch backport-PRs aanmaakt.

### Hoe het werkt

1. **Label toevoegen**: Voeg labels toe aan een PR die al naar `main` is gemerged:
   - `backport release/1.2`: Automatische backport naar `release/1.2`
   - `backport release/1.1`: Automatische backport naar `release/1.1`
   - Combineer labels als je naar meerdere branches wilt backporten

2. **Comment triggers**: Je kan ook comments gebruiken om backports in gang te zetten:
   ```
   @backport-bot backport to release/1.2
   @backport-bot backport to release/1.1,release/1.0
   ```

3. **Automatische PR**: backport.yml maakt automatisch een nieuwe PR aan naar de doelbranch met:
   - Cherry-picked commits
   - Dezelfde titel + `[backport release/X.Y]` suffix
   - Link naar originele PR
   - Automatische labels (bv. `backport`)

### Workflow met Backports

**Scenario: Hotfix moet naar meerdere actieve versies**

```bash
# 1. Maak en merge hotfix naar main (trunk)
git checkout main
git checkout -b hotfix/security-issue
# ... fix en test ...
git merge hotfix/security-issue

# 2. In GitHub UI: Label de PR met `backport release/1.2` en `backport release/1.1`
# → backport.yml maakt automatisch PRs naar beide branches

# 3. Review de backport-PRs in GitHub
# 4. Merge ze als ze klaar zijn
# 5. Tag en release van elke release branch
```

### Best Practices

- Voeg backport-labels **na** merge naar `main` toe (niet daarvoor)
- Voor kritieke hotfixes: tagging direct na backport, voor features: batch meerdere changes
- Zorg dat CI/CD tests groen zijn voor elke backport-PR
- Merk in je CHANGELOG aan welke versies de fix krijgen

**Configuratie:** [.github/backport.yml](./.github/backport.yml)

## Changelog Beheer

Bij elke release:

1. Update [CHANGELOG.md](../CHANGELOG.md) met nieuwe versie
2. Noteer:
   - Nieuwe features (MINOR)
   - Bug fixes (PATCH)
   - Breaking changes (MAJOR)

**Voorbeeld:**
```markdown
## [0.2.0] - 2026-01-09

### Added
- Nieuwe gebruiker dashboard
- Export naar CSV functionaliteit

### Fixed
- Bug in login flow
- Performance issues in search

### Changed
- API endpoints gemigreerd naar v2

### Breaking Changes
- Oude API v1 endpoints verwijderd
```

## Release Checklist

Voordat je een release maakt:

- [ ] Alle features getest in test environment
- [ ] QA approval ontvangen
- [ ] Tests groen op de branch waar je de release van maakt
- [ ] CHANGELOG.md geupdate
- [ ] Release notes voorbereid
- [ ] Product Owner approval


---

Vragen over het release process? Contacteer [Michel Verhoeven](michel@woweb.nl).
