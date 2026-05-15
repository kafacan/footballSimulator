# Insider Champions League Simulator

A session-based football tournament simulator built with Laravel, Vue 3, and Tailwind CSS.

The project supports:
- UEFA Champions League style tournaments
- National league simulations
- Group stages
- Knockout stages
- Match simulation
- Standings calculation
- Prediction systems
- Session persistence
- Interactive tournament progression

---

# Features

## Champions League Mode

- Old-format Champions League simulation
- 8 groups / 32 teams
- Pot-based group generation
- Group standings
- Round of 16
- Quarter Finals
- Semi Finals
- Final
- Aggregate score resolution
- Tournament winner predictions
- Match editing
- Session persistence

## National League Mode

- Create custom leagues from selected teams
- 4–18 teams
- Double round-robin fixture generation
- League standings
- Champion resolution
- Session persistence
- Prediction system

## Simulation Features

- Match score simulation
- Elo-based prediction system
- Session progression
- Knockout progression
- Match result editing
- Deterministic standings
- Historical prediction tracking

---

# Tech Stack

## Backend

- Laravel
- PHP
- MySQL (development)
- Feature-tested service architecture

## Frontend

- Vue 3
- Tailwind CSS
- Atomic Design architecture
- Design token system

---

# Project Architecture

## Backend Architecture

The backend is session-based and service-driven.

### Controllers

Controllers are access points only.

Responsibilities:
- validate requests
- call services
- return responses

Controllers must not contain tournament business logic.

Main controllers:
- `GameSessionController`
- `TeamController`

---

### Models

Models represent entities and relationships.

Examples:
- `GameSession`
- `MatchGame`
- `Group`
- `Team`

Simple query helpers/scopes may live on models, but heavy business logic belongs in services.

---

### Services

Services own tournament logic.

Main services:

#### Core Tournament Services

- `FixtureService`
- `StandingsService`
- `MatchSimulationService`
- `PredictionService`
- `KnockoutGenerationService`
- `KnockoutResolutionService`
- `EloRatingService`

#### Game Mode Services

- `ChampionsLeagueGameModeService`
- `NationalLeagueGameModeService`

#### State Building

- `SessionStateBuilder`

---

### Service Responsibilities

#### MatchSimulationService

Responsible for:
- realistic score generation
- single match simulation
- collection simulation

The service is intentionally mode-neutral.

---

#### ChampionsLeagueGameModeService

Responsible for:
- group stage progression
- knockout generation
- knockout progression
- champion synchronization
- current stage synchronization

---

#### NationalLeagueGameModeService

Responsible for:
- national league progression
- current week synchronization
- champion synchronization
- finishing state handling

---

#### PredictionService

Responsible for:
- group predictions
- tournament winner predictions
- prediction history
- Monte Carlo tournament simulation

Predictions use:
- Elo ratings
- standings state
- simulated remaining fixtures

---

# Match Generation Algorithm

## Group / League Fixtures

The fixture generation flow is deterministic and service-driven.

### Process

1. Validate participating teams
2. Ensure even team count
3. Generate round-robin pairings
4. Generate first-leg fixtures
5. Generate reverse fixtures
6. Persist fixtures by:
   - session
   - stage
   - week
   - group
   - home team
   - away team

---

## Champions League Group Generation

### Process

1. Seed teams into pots
2. Create 8 groups
3. Place one team from each pot into every group
4. Generate 6 weeks of fixtures
5. Calculate standings
6. Advance top 2 teams from every group

---

## Knockout Generation

### Process

1. Generate Round of 16 pairings
2. Generate Quarter Finals
3. Generate Semi Finals
4. Generate single-leg Final
5. Resolve winners by aggregate score
6. No away goals rule
7. Keep tie resolution deterministic
8. Invalidate downstream rounds if earlier knockout results change

---

# Prediction System

The project uses simulation-driven predictions.

## Group Predictions

Predictions:
- become available after enough completed matches
- simulate remaining fixtures
- use Elo-informed probabilities
- generate probabilistic league winners

## Knockout Predictions

Predictions:
- simulate future knockout rounds
- generate tournament winner probabilities
- support prediction history by stage

---

# Frontend Architecture

The frontend uses Atomic Design and a shared design-token layer.

---

## Design Layer

Location:

```txt
resources/js/design
```

Contains:
- colors
- spacing
- radius
- layers
- tokens
- variants

The design layer is the source of truth for reusable UI styling decisions.

---

## Atomic Design Structure

### Atoms

Primitive UI building blocks.

Examples:
- `UiButton`
- `IconButton`
- `UiCard`
- `UiBadge`
- `SelectField`
- `ScoreInput`
- `ModalShell`
- `LoadingSpinner`

---

### Molecules

Reusable UI patterns built from atoms.

Examples:
- `MatchCard`
- `LeagueTable`
- `PredictionPanel`
- `SectionHeader`
- `StepNavigator`
- `EditMatchResultModal`
- `SessionMeta`

---

### Organisms

Larger domain-aware UI sections.

Examples:
- `GroupStagePanel`
- `KnockoutStagePanel`
- `TournamentStatusBar`
- `FixturePanel`
- `SessionList`
- `DashboardHeader`
- `NationalTeamSelection`

---

### Pages

Pages are route-level coordinators.

Responsibilities:
- load state
- coordinate API interactions
- pass props/events downward

Pages should remain thin and avoid duplicated UI markup.

---

# API Rules

- The application is fully session-based.
- Game state is accessed through session endpoints.
- Match mutations are always session-scoped.
- Backend remains the source of truth.
- Frontend does not recalculate official standings or tournament winners.

---

# Testing

The project includes feature tests for:
- fixture generation
- standings
- session flows
- knockout progression
- match simulation
- match editing
- validation
- prediction behavior

Run tests:

```bash
php artisan test
```

---

# Frontend Development

Install dependencies:

```bash
npm install
```

Run Vite:

```bash
npm run dev
```

Build frontend:

```bash
npm run build
```

---

# Backend Development

Run Laravel server:

```bash
php artisan serve
```

Run formatting:

```bash
./vendor/bin/pint
```

---

# Project Rules

## Backend Rules

- Controllers stay thin
- Business logic belongs in services
- Shared reusable queries may live on models
- Session state responses should stay centralized
- Match simulation should remain mode-neutral
- Game mode progression belongs in mode services

---

## Frontend Rules

- Use Atomic Design consistently
- Reuse atoms before writing raw repeated Tailwind markup
- Keep pages thin
- Use the design token layer
- Avoid duplicated styling decisions
- Backend remains the source of truth for tournament state

---

# Future Improvements

Potential future improvements:
- authentication and user-based session ownership
- richer prediction and simulation models
- multiplayer/shared tournament sessions
- cached prediction calculations
- additional tournament formats