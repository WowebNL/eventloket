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

Dit project volgt een **eenvoudig lineair workflow**:

1. **Development** → Feature branches van `main` (label PRs met changelog labels)
2. **Testing & QA** → Op `main` branch, gemergde PR's worden uitgerold naar de testomgeving
3. **Release Draft** → Een maintainer zet een release-draft klaar op een vaste, geteste commit
4. **Release** → Bij het publiceren van de draft maakt GitHub de tag op die commit, op `main` (trunk) of op de relevante `release/*` branch bij backports
5. **Deploy** → Naar productie via release tag

### Branch Strategie

```
main (stabiel, getest)
├── release/1.2 (actieve versie met backports)
├── feat/user-authentication (label: changelog: feature)
├── fix/login-issue (label: changelog: bug)
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
git checkout -b feat/beschrijving-van-feature

# Werk eraan, commit regelmatig
git add .
git commit -m "description"
git push origin feat/beschrijving-van-feature

# Open Pull Request naar main
# - Code review
# - Tests moeten groen zijn
# - Label de PR met een changelog label (zie tabel hieronder)
# - Merge naar main
```

### Changelog Labels

Label je Pull Requests met één van deze labels. De labels bepalen in welke categorie een PR in de release notes komt en hoe de versie wordt verhoogd:

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
- PR's worden automatisch gelabeld via de branch naam (`feat/...` → feature label, `fix/...` → bug label) en de titel (zie [.github/release-drafter.yml](../.github/release-drafter.yml); alleen de autolabeler van Release Drafter is actief)
- Je kan handmatig aanpassingen maken
- Zorg dat elke PR minimaal één changelog label heeft

### Tests en QA op Main

Na merge naar `main`:

1. CI/CD pipeline runt automatisch tests
2. Code is beschikbaar op test/staging server
3. QA team test de feature
4. Bugs? → Maak bugfix branch en merge terug
5. Alles stabiel en getest? → Release maken

## Release Proces

Releases worden niet automatisch klaargezet. Een maintainer zet een release-draft klaar op een vaste commit die getest is, en publiceert die na review. Zo ligt vast welke code er in een release zit, ook als er na het testen nog PR's naar `main` worden gemerged.

### Release Stappen

#### Stap 1: Kies de commit en de versie

- Kies de commit die op de testomgeving is getest (op `main`, of op de relevante `release/*` branch bij backports)
- Bepaal de nieuwe versie op basis van de changelog labels van de PR's sinds de vorige release (zie [Versie-berekening](#versie-berekening))

#### Stap 2: Zet de release-draft klaar

Maak in GitHub een draft release aan met:
- De tag-naam van de nieuwe versie (bijv. `v0.2.0`), nog zonder de tag zelf aan te maken
- Als target de volledige commit-sha uit stap 1, niet een branch naam
- Release notes met de gemergde PR's sinds de vorige release, gegroepeerd per categorie:
   - 💥 Breaking changes
   - ✨ New features
   - 🔒 Security
   - 🐛 Bug Fixes
   - 📝 Documentation
   - ♻️ Refactor
   - ⬆️ Dependency Updates

PR's met het label `skip-changelog` laat je weg uit de release notes.

#### Stap 3: Review & Aanpassingen

In GitHub:
- Controleer de release notes en de target commit
- Edit titel, beschrijving, changelog indien nodig
- Voeg extra info toe

#### Stap 4: Publish Release

```bash
# In GitHub UI:
# Draft Release → "Publish Release"
```

Dit doet automatisch:
- Creates git tag (bijv. `v0.2.0`) op de target commit van de draft
- Publiceert de release op GitHub
- Triggert deployment naar productie (via CI/CD)

#### Stap 5: Deploy naar Productie

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
   └─ Getest op de testomgeving
      └─ Maintainer zet draft v0.2.0 klaar op de geteste commit
         └─ Review in GitHub
            └─ Publish Release
               └─ Git tag: v0.2.0
                  └─ CI/CD deploy triggered
```

### Versie-berekening

De volgende versie volgt uit de labels van de PR's sinds de vorige release:

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
- Vermeld in de release notes welke versies de fix krijgen

**Configuratie:** [.github/backport.yml](./.github/backport.yml)

## Changelog Beheer

De release notes op de [GitHub Releases pagina](https://github.com/WowebNL/eventloket/releases) zijn de changelog. Je stelt ze samen in stap 2 van het release proces en publiceert ze met de release.

[CHANGELOG.md](../CHANGELOG.md) bevat de historie tot en met v1.2.0 en wordt niet meer bijgewerkt.

## Release Checklist

Voordat je een release maakt:

- [ ] Alle features getest in test environment
- [ ] QA approval ontvangen
- [ ] Tests groen op de branch waar je de release van maakt
- [ ] Release notes voorbereid
- [ ] Product Owner approval


---

Vragen over het release process? Contacteer [Michel Verhoeven](michel@woweb.nl).
