# ADR-009 — local agent context system (the project context file)

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+

## Contexte

Le développement de Polysource est assisté par **[anonymised] 4.7** (et possiblement d'autres outils internes à l'avenir). Sans système de persistance de contexte, chaque nouvelle session demande à l'utilisateur de réexpliquer :
- ce qu'est Polysource
- où en est le projet
- quelles sont les conventions de code
- quelles décisions ont été prises et lesquelles sont encore ouvertes
- quelles parties sont publiques vs privées

the upstream tooling the local agent lit automatiquement un fichier `the project context file` à la racine du projet ouvert. Cette convention permet de fournir un contexte persistant lu à chaque nouvelle session.

Le défi spécifique pour Polysource : la doc d'analyse interne ne doit **pas** être committée (cf. décision utilisateur), mais elle est précieuse pour l'IA. Il faut donc **deux niveaux** de contexte : public (commité) et local privé (non commité).

## Options envisagées

### Option A — Aucun contexte persistant

Réexpliquer à chaque session.

**Pour** : zéro fichier supplémentaire.
**Contre** : 5-10 minutes perdues à chaque session, risque de divergence (l'utilisateur n'explique pas tout, l'IA part dans une mauvaise direction).

### Option B — `the project context file` racine public seulement

Un seul fichier markdown à la racine, commité dans le repo.

**Pour** : standard the upstream tooling, simple.
**Contre** : ne couvre pas la partie privée (analyse interne).

### Option C — `the project context file` public + `.local/CLAUDE.local.md` privé (gitignored)

Deux fichiers complémentaires. Le local pointe vers `/path/to/private-notes/`.

**Pour** :
- Le mainteneur a tout le contexte (public + privé) localement.
- Le repo public n'expose pas la stratégie interne.
- Les contributeurs externes ont juste le contexte public, suffisant pour comprendre le code et les ADR.

**Contre** :
- 2 fichiers à maintenir.
- Le mainteneur doit penser à mettre à jour les deux.

### Option D — `the project context file` + `AGENTS.md`

`the project context file` pour [anonymised], `AGENTS.md` pour autres IA (Cursor, Cline, etc.). Convention émergente OSS.

**Pour** : vendor-agnostic.
**Contre** : duplication de contenu. Aujourd'hui, la majorité des IA peuvent lire `the project context file` ou un fichier équivalent ; `AGENTS.md` est encore en train de s'imposer.

## Décision

**Option C — `the project context file` public + `.local/CLAUDE.local.md` privé (gitignored)** est retenue.

Possibilité de basculer vers Option D plus tard (ajouter `AGENTS.md` symlink vers `the project context file`) si la convention `AGENTS.md` se généralise.

### Structure

```
/var/www/polysource/                 (REPO PUBLIC)
├── the project context file                         ← public, commité, lu auto par the local agent
├── .local/
│   └── CLAUDE.local.md               ← gitignored, pointeur vers privé
├── .gitignore                        ← .local/CLAUDE.local.md exclu
└── ...

/path/to/private-notes/     (HORS REPO)
├── the project context file                         ← contexte privé pour [anonymised]
├── architecture/                     ← 3 docs analyse interne
├── strategy/                         ← 4 docs stratégie interne
├── comparisons/                      ← 1 doc concurrence
├── use-cases/                        ← 1 doc cas d'usage
└── risks/                            ← 1 doc risques
```

### Contenu de `the project context file` racine (public)

Contient :
- description courte du projet (3 lignes)
- état actuel (version, phase)
- conventions de code (PHP 8.4 patterns, namespaces, tags DI, etc. — cf. ADR-007)
- contraintes architecturales clés (immutabilité, pas de fuite Doctrine, etc.)
- pointeurs vers la doc publique (`docs/README.md`, `docs/adr/`, `docs/roadmap/development-plan.md`)
- instructions de session pour l'IA (« avant tout PR, lire les ADR ; respecter le scope strict ; ne pas concurrencer EasyAdmin »)
- pointeur vers `.local/CLAUDE.local.md` si présent

### Contenu de `.local/CLAUDE.local.md` (gitignored)

Contient :
- chemin du dossier privé : `/path/to/private-notes/`
- résumé exécutif des décisions stratégiques (fork rejeté, positionnement vs EasyAdmin, etc.)
- pointeurs vers les analyses internes (cartographie EasyAdmin, couplage Doctrine, comparaisons concurrents, risques business)
- consignes spécifiques pour le mainteneur (« ne pas exposer ces docs en commit, en PR, ou en discussion publique »)

### Contenu de `/path/to/private-notes/the project context file`

Index des 10 docs internes + résumé synthétique pour permettre à [anonymised] de naviguer rapidement.

### Mise à jour du contexte

Convention :
- Modifier `the project context file` quand : nouvelle ADR acceptée, nouvelle phase commencée, conventions changées.
- Modifier `.local/CLAUDE.local.md` quand : la stratégie interne évolue.
- Modifier `/path/to/private-notes/the project context file` quand : on ajoute/retire un doc privé.

Au minimum, **avant chaque release majeure**, faire une passe de revue des 3 fichiers pour s'assurer qu'ils sont à jour.

## Conséquences

### Positives

- **Contexte instantané** à chaque nouvelle session avec [anonymised] 4.7.
- **Pas d'exposition** de la stratégie interne dans le repo public.
- **Onboarding contributeurs** : `the project context file` public sert aussi de doc pour les humains qui lisent en premier ce fichier.
- **Standard the upstream tooling** : the local agent (CLI) lit `the project context file` automatiquement.
- **Évolutif** : on peut ajouter `AGENTS.md` plus tard si la convention vendor-agnostic s'impose.

### Négatives

- **3 fichiers à synchroniser** : risque de dérive. Mitigation : revue pré-release.
- **Le mainteneur doit penser à updater** : pas automatisé.
- **Si le mainteneur change de machine** : le `private-notes/` doit être copié manuellement (ou versionné dans un repo privé séparé — décision future).

### Convention de mise à jour automatique

Une cible Makefile peut être ajoutée pour aider :

```makefile
context-check:      ## Vérifie que the project context file mentionne bien la dernière ADR
	@last_adr=$$(ls docs/adr/0*.md | sort | tail -1) && \
	if ! grep -q "$$(basename $$last_adr)" the project context file; then \
		echo "⚠️ the project context file ne mentionne pas $$last_adr"; exit 1; \
	fi
```

À ajouter à la CI pour garantir que le `the project context file` reste à jour avec les ADR.

## Références

- [the upstream tooling the local agent: the project context file convention](https://docs.anthropic.com/en/docs/claude-code/memory)
- EasyAdmin v5 utilise déjà un `AGENTS.md` (10 KB) — modèle pour notre `the project context file` public
- [agents.md emerging convention](https://agents.md/)
