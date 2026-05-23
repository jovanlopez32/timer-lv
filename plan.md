# Plan de implementación — Timer App

## Resumen del flujo

- **Página raíz `/`** → solo muestra la palabra **"timer"** (sin login).
- **Login admin** (`/login`) → para Jorge Linan, que entra al panel a gestionar agentes y tipos de tarea.
- **Panel admin** (`/dashboard`) → Jorge crea/lista **agentes** (name, slug, brand) y **tipos de tarea** (name). El slug genera la URL pública del agente.
- **Ruta pública por agente** (`/{slug}`, p. ej. `/luis-hurtado`) → cualquiera con el link puede iniciar/pausar/completar timers a nombre de ese agente. Sin login.
- **Modelo de tiempo real**: cada vez que el agente arranca o reanuda, se abre una "sesión de timer" con `started_at`. Al pausar, se cierra con `ended_at`. Sumando la duración de todas las sesiones de un timer se obtiene el tiempo neto efectivo (excluye comidas/baños). Al marcar como completado se calcula `decimal_hours` y se guarda.

> Ejemplo: 8:00→10:00 (2h) + 11:00→12:00 (1h) = `3.0` horas. Si fueran 3h30m → `3.5`.

---

## 1. Migraciones

Crear (en este orden, con `php artisan make:migration`):

### 1.1 `agents`
- `id` (bigIncrements)
- `name` (string)
- `slug` (string, unique, index) — usado para la URL pública
- `brand` (string) — la marca donde trabaja
- `timestamps()`

### 1.2 `task_types`
- `id`
- `name` (string, unique)
- `timestamps()`

### 1.3 `timers`
- `id`
- `agent_id` (foreignId → agents, cascade on delete)
- `task_type_id` (foreignId → task_types, restrict on delete)
- `started_at` (timestamp) — primera vez que se inició
- `ended_at` (timestamp, nullable) — cuando se marcó como completado
- `decimal_hours` (decimal(8,2), nullable) — total final en horas decimales (p. ej. 3.50)
- `completed` (boolean, default false, index)
- `timestamps()`
- Índice compuesto `(agent_id, completed)` para listar el "timer activo" rápido.

### 1.4 `timer_sessions`
- `id`
- `timer_id` (foreignId → timers, cascade on delete)
- `started_at` (timestamp)
- `ended_at` (timestamp, nullable) — null = sesión activa (corriendo ahora mismo)
- `timestamps()`
- Índice `(timer_id, ended_at)` para encontrar la sesión activa.

**Regla de negocio**: solo puede haber **una** `timer_sessions` con `ended_at = null` por `timer_id` a la vez (la sesión actualmente corriendo). El backend lo refuerza, no la DB.

---

## 2. Modelos

### 2.1 `App\Models\Agent`
- `#[Fillable(['name', 'slug', 'brand'])]`
- `getRouteKeyName(): string` → retorna `'slug'` (route model binding por slug).
- Relación `timers(): HasMany`.

### 2.2 `App\Models\TaskType`
- `#[Fillable(['name'])]`
- Relación `timers(): HasMany`.

### 2.3 `App\Models\Timer`
- `#[Fillable(['agent_id', 'task_type_id', 'started_at', 'ended_at', 'decimal_hours', 'completed'])]`
- Casts: `started_at` y `ended_at` → datetime, `completed` → bool, `decimal_hours` → decimal:2.
- Relaciones: `agent()`, `taskType()`, `sessions()` (HasMany TimerSession ordenado por started_at).
- Scope `active()` → `where completed = false`.
- Método `currentSession(): ?TimerSession` → la sesión con `ended_at` null.
- Método `elapsedSeconds(): int` → suma de duración de todas las sesiones (cerradas + la activa hasta `now()`).

### 2.4 `App\Models\TimerSession`
- `#[Fillable(['timer_id', 'started_at', 'ended_at'])]`
- Casts: timestamps → datetime.
- Relación `timer()`.

---

## 3. Quitar referencias de registro (sin tocar el código de Fortify)

La instrucción del usuario es: **quitar solo las referencias, no el código**. Cambios mínimos:

1. **`resources/js/pages/Welcome.vue`** — Se reescribirá completamente para mostrar solo "timer" (ver sección 6.1). Esto elimina los links a `register()` y `login()`.
2. **`resources/js/pages/auth/Login.vue`** — eliminar el bloque inferior `"Don't have an account? Sign up"` (líneas 105-108) y el import `register` de `@/routes`.

> No tocamos `routes/web.php` de Fortify (las rutas siguen vivas), ni `app/Actions/Fortify/CreateNewUser.php`, ni `FortifyServiceProvider`. Solo escondemos los enlaces en el frontend.

---

## 4. Seeder

### 4.1 `DatabaseSeeder`
Sustituir el seed actual (`Test User`) por:

1. Crear usuario admin **Jorge Linan**:
   - `name`: `Jorge Linan`
   - `email`: `jorge.linan@leadventure.com`
   - `password`: `Password` (Bcrypt vía cast `hashed`)
   - `email_verified_at`: `now()` (para no chocar con middleware `verified`).

2. Llamar a `AgentSeeder` y `TaskTypeSeeder`.

### 4.2 `AgentSeeder`
Crear un agente de ejemplo: `Luis Hurtado` con `slug: luis-hurtado`, `brand: Leadventure`. Esto permite probar la ruta `/luis-hurtado` inmediatamente.

### 4.3 `TaskTypeSeeder`
Sembrar tipos de tarea básicos: `Diseño`, `Desarrollo`, `QA`, `Reunión`, `Investigación`. (Si el usuario quiere otros, los añade desde el admin.)

---

## 5. Rutas

### 5.1 `routes/web.php`

```
GET  /                            → Inertia 'Welcome' (solo muestra "timer")     [name: home]
GET  /login                       → Fortify (sin cambios)
POST /login                       → Fortify
POST /logout                      → Fortify

// Admin (auth + verified)
GET    /dashboard                 → DashboardController@index                    [name: dashboard]
GET    /admin/agents              → AgentController@index                        [name: agents.index]
POST   /admin/agents              → AgentController@store                        [name: agents.store]
DELETE /admin/agents/{agent}      → AgentController@destroy                      [name: agents.destroy]
GET    /admin/task-types          → TaskTypeController@index                     [name: task-types.index]
POST   /admin/task-types          → TaskTypeController@store                     [name: task-types.store]
DELETE /admin/task-types/{taskType} → TaskTypeController@destroy                 [name: task-types.destroy]

// Público — al final del archivo para no chocar con rutas estáticas
GET  /{agent:slug}                → PublicTimerController@show                   [name: agent.timer]
POST /{agent:slug}/timers         → PublicTimerController@start                  [name: agent.timer.start]
POST /timers/{timer}/pause        → PublicTimerController@pause                  [name: agent.timer.pause]
POST /timers/{timer}/resume       → PublicTimerController@resume                 [name: agent.timer.resume]
POST /timers/{timer}/complete     → PublicTimerController@complete               [name: agent.timer.complete]
```

> La ruta `/{agent:slug}` va al final del archivo (después de cargar `settings.php` y otras rutas con nombre fijo) para que rutas como `/login`, `/dashboard`, etc., tengan prioridad.

---

## 6. Controladores

### 6.1 `PublicTimerController`

#### `show(Agent $agent)`
- Carga el timer activo del agente: `Timer::where('agent_id', $agent->id)->where('completed', false)->with(['taskType', 'sessions'])->first()`.
- Calcula `elapsedSeconds` y si hay sesión corriendo (para que el frontend sepa si empezar contando).
- Carga todos los `TaskType::orderBy('name')->get()`.
- Render `'PublicTimer'` con props: `agent`, `taskTypes`, `activeTimer` (puede ser null).

#### `start(Request, Agent $agent)`
- Valida `task_type_id` (exists). Rechaza si ya hay timer activo del agente.
- Crea `Timer` con `started_at: now(), completed: false`.
- Crea `TimerSession` con `started_at: now(), ended_at: null`.
- Redirect back con flash → Inertia recarga props con el nuevo `activeTimer`.

#### `pause(Timer $timer)`
- Autoriza que el timer no esté completado.
- Busca sesión activa (`ended_at = null`) y le pone `ended_at = now()`.
- Redirect back.

#### `resume(Timer $timer)`
- Autoriza que no esté completado y que no haya sesión abierta.
- Crea nueva `TimerSession` con `started_at: now(), ended_at: null`.
- Redirect back.

#### `complete(Timer $timer)`
- Si hay sesión abierta, ciérrala con `now()`.
- Suma duración de todas las sesiones, convierte a horas decimales (con 2 decimales → `round($seconds / 3600, 2)`).
- Marca `completed = true`, `ended_at = now()`, `decimal_hours = $hours`.
- Redirect back.

### 6.2 `AgentController` (admin)
- `index` → Inertia con lista de agentes.
- `store` → valida `name`, `brand`; genera `slug` con `Str::slug` (unique check). Crea agente. Redirect.
- `destroy` → borra agente (cascade borra sus timers).

### 6.3 `TaskTypeController` (admin)
- `index` → Inertia con lista de task types.
- `store` → valida `name` (unique). Crea. Redirect.
- `destroy` → si hay timers que lo usan, falla con mensaje (FK restrict).

### 6.4 `DashboardController` (admin)
- `index` → Render `Dashboard` con conteo de agentes y task types, y los últimos 10 timers completados.

---

## 7. UI (Vue + Inertia + shadcn-vue)

> Componentes shadcn-vue ya instalados: button, input, label, card, dropdown-menu, select, dialog. Faltan: **command** (para el select con search), **table** (para listas del admin), **separator** (ya está). Se instalarán vía el MCP shadcn-vue antes de empezar.

### 7.1 `resources/js/pages/Welcome.vue` — reescritura completa
- Pantalla centrada, fondo neutro, una sola línea: `timer` en tipografía grande, mono o sans, en minúsculas. Soporte dark mode. Sin nav, sin links a login/register.

### 7.2 `resources/js/pages/auth/Login.vue` — limpieza
- Quitar el bloque "Don't have an account? Sign up".
- Quitar el import `register`.

### 7.3 `resources/js/pages/PublicTimer.vue` — pantalla pública del agente
Estructura:
1. **Header simple**: nombre del agente + brand pequeño abajo.
2. **Selector de tipo de tarea** (siempre visible):
   - Componente shadcn `Combobox` (Popover + Command) — input con búsqueda fuzzy.
   - Si `activeTimer` existe, el select muestra el task type del timer activo, deshabilitado.
3. **Contador (timer)**:
   - **Aparece con transición `fade` SOLO la primera vez** que se selecciona un task type viniendo desde estado vacío. Si el usuario cambia de task type ya existiendo selección, no se vuelve a animar. Implementación: una `ref<boolean>` `hasShownTimer` que se vuelve true en el primer cambio de vacío→valor, y se mantiene true.
   - Muestra `HH:MM:SS` actualizado cada segundo con `setInterval` mientras la sesión esté corriendo. Inicia desde `elapsedSeconds` recibido del backend.
4. **Botones**:
   - **Play** (icono ▶ — `Play` de lucide-vue-next): visible si no hay sesión activa. Click → POST a `start` (si no hay timer) o `resume` (si hay timer pausado).
   - **Pause** (icono ⏸ — `Pause` de lucide-vue-next): visible solo si la sesión está corriendo. Click → POST a `pause`.
   - **Play y Pause se intercambian en el mismo lugar** (uno se oculta, el otro aparece). No se muestran ambos.
   - **Mark as completed** (`Button` variant default): visible cuando hay un timer activo (corriendo o pausado). Click → POST a `complete`. Cierra el timer y limpia la UI.
5. **Tras completar**: toast/sonner "Timer guardado: 3.5 horas" y resetea selector + estado.

### 7.4 Admin pages
- **`resources/js/pages/Dashboard.vue`** (modificar): tarjetas con número de agentes, tipos de tarea, timers totales; tabla de últimos 10 timers (agente, tarea, horas, fecha).
- **`resources/js/pages/admin/Agents.vue`** (nuevo): tabla con agentes (name, slug, brand, link público copiable) + form para crear nuevo + botón eliminar.
- **`resources/js/pages/admin/TaskTypes.vue`** (nuevo): tabla simple + form para crear.

### 7.5 Navegación admin
- En `AppHeader.vue` agregar links a `/admin/agents` y `/admin/task-types`. Ya hay un link `Dashboard`.

---

## 8. Wayfinder

Después de crear los controladores y rutas, ejecutar `npm run dev` o `php artisan wayfinder:generate` para generar los TypeScript helpers (`@/actions`, `@/routes`). En las páginas Vue se importan en lugar de hardcodear URLs.

---

## 9. Tests (PHPUnit Feature)

Mínimo:

1. **`tests/Feature/PublicTimerTest.php`**
   - `it_shows_the_agent_timer_page_with_task_types`
   - `it_starts_a_timer_and_creates_a_session`
   - `it_pauses_a_running_session`
   - `it_resumes_after_a_pause_creating_a_new_session`
   - `it_completes_a_timer_and_calculates_decimal_hours` (caso 8→10, 11→12 = 3.0)
   - `it_calculates_decimal_hours_with_half_hour` (caso → 3.5)
   - `it_prevents_starting_a_second_timer_for_the_same_agent`

2. **`tests/Feature/Admin/AgentsTest.php`**
   - `admin_can_list_create_and_delete_agents`
   - `slug_is_auto_generated_and_unique`
   - `guests_cannot_access_admin_routes`

3. **`tests/Feature/Admin/TaskTypesTest.php`**
   - `admin_can_create_and_delete_task_types`

> Para el cálculo decimal, los tests usarán `Carbon::setTestNow()` para forzar tiempos exactos. Esto satisface la regla "los tests prueban funcionalidad real".

---

## 10. Factories

- `AgentFactory` — name (fake company), slug (Str::slug del name + sufijo random para unicidad), brand (fake company).
- `TaskTypeFactory` — name (fake jobTitle).
- `TimerFactory` — started_at random pasado, completed false, sin sesiones (las sesiones se crean en estados específicos).
- `TimerSessionFactory` — started_at, ended_at random.

---

## 11. Orden de ejecución

1. Crear migraciones (sección 1) y correr `php artisan migrate:fresh`.
2. Crear modelos (sección 2).
3. Crear factories (sección 10).
4. Reescribir `Welcome.vue` + limpiar `Login.vue` (sección 3 + 7.1 + 7.2).
5. Actualizar `DatabaseSeeder` + crear `AgentSeeder` y `TaskTypeSeeder` (sección 4). Correr `php artisan db:seed`.
6. Crear controladores y rutas (secciones 5 + 6).
7. Instalar componentes shadcn-vue faltantes (`command`, `table`) vía MCP.
8. Crear `PublicTimer.vue` (sección 7.3).
9. Actualizar `Dashboard.vue` + crear páginas admin (sección 7.4 + 7.5).
10. Generar Wayfinder (sección 8). Correr `npm run dev`.
11. Escribir tests (sección 9). Correr `php artisan test --compact`.
12. `vendor/bin/pint --dirty --format agent` para formato PHP.

---

## 12. Decisiones a confirmar antes de codear

1. **¿La página pública `/{slug}` debe ser totalmente anónima?** El usuario lo describió así, pero significa que cualquiera con el link puede iniciar/parar timers a nombre del agente. Asumo SÍ (lo que pidió). Si no, se añadiría un `password` simple al `Agent` para validar.
2. **Una sola tarea activa a la vez por agente.** Asumo SÍ (el flujo lo sugiere). Si quiere paralelas, hay que quitar la validación.
3. **¿`decimal_hours` con 2 decimales** (3.50) o solo 1 (3.5)? Voy con 2 para precisión, el frontend mostrará la versión recortada.
4. **Marca del agente** = string libre (no una tabla `brands` separada). El usuario dijo "solo debe contener el nombre, slug y la marca", parece un campo texto.
