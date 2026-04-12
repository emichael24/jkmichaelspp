# JK Michaels Professional Mastery Platform — Frontend Architecture

## Document Control

| Field | Value |
|---|---|
| Version | 1.0 |
| Date | February 26, 2026 |
| Framework | Vue.js 3 (Composition API) |
| Build | Vite |
| Routing | Inertia.js (server-side routing) |
| State | Pinia |
| UI Library | Custom components + Tailwind CSS |
| Mobile | PWA + Capacitor |

---

## 1. Technology Decisions

### Why Vue 3 + Inertia.js
- **Vue 3:** Composition API for clean code organization, excellent reactivity, strong ecosystem
- **Inertia.js:** Eliminates the need for a separate API for the frontend — server-side routing with SPA feel. Laravel controllers return Inertia responses directly. No need for separate REST API calls for the web frontend.
- **API endpoints** (from doc 03) are used by: Capacitor mobile app, third-party integrations, and any external consumers. The web PWA uses Inertia for page-level data and API for dynamic interactions (e.g., quiz answering, real-time updates).

### Why Tailwind CSS
- Utility-first for rapid UI development
- Consistent design system
- Easy dark mode / theme support
- Small production bundle (PurgeCSS)
- Mobile-first responsive classes

### Why Pinia
- Official Vue 3 state management
- TypeScript support
- Devtools integration
- Simpler than Vuex

---

## 2. Project Structure

```
resources/
├── js/
│   ├── app.js                      # Entry point
│   ├── bootstrap.js                # Axios, Echo, global config
│   │
│   ├── Layouts/
│   │   ├── AppLayout.vue           # Main authenticated layout
│   │   ├── AuthLayout.vue          # Login/register layout
│   │   ├── AdminLayout.vue         # Admin console layout
│   │   ├── FacilitatorLayout.vue   # Facilitator console layout
│   │   ├── SponsorLayout.vue       # Sponsor portal layout
│   │   └── OnboardingLayout.vue    # Onboarding flow layout
│   │
│   ├── Pages/                      # Inertia pages (mapped to routes)
│   │   ├── Auth/
│   │   │   ├── Login.vue
│   │   │   ├── Register.vue
│   │   │   ├── ForgotPassword.vue
│   │   │   └── ResetPassword.vue
│   │   │
│   │   ├── Onboarding/
│   │   │   ├── Welcome.vue
│   │   │   ├── GoalSelection.vue
│   │   │   ├── ProfileSetup.vue
│   │   │   ├── Diagnostic.vue
│   │   │   └── QuickWin.vue
│   │   │
│   │   ├── Home/
│   │   │   └── Index.vue           # Learner home / daily engine
│   │   │
│   │   ├── Courses/
│   │   │   ├── Index.vue           # Course catalog
│   │   │   ├── Show.vue            # Course detail
│   │   │   └── Lesson.vue          # Lesson page (multi-tab)
│   │   │
│   │   ├── Assessments/
│   │   │   ├── QuizPlayer.vue      # Active quiz UI
│   │   │   ├── QuizReview.vue      # Post-quiz review
│   │   │   ├── MockExamPlayer.vue  # Mock exam UI
│   │   │   ├── MockExamReport.vue  # Post-mock report
│   │   │   └── Practice.vue        # Practice by domain
│   │   │
│   │   ├── SpacedRepetition/
│   │   │   └── Review.vue          # Flashcard review session
│   │   │
│   │   ├── Simulations/
│   │   │   ├── Index.vue           # Simulation listing
│   │   │   └── Player.vue          # Simulation player
│   │   │
│   │   ├── Analytics/
│   │   │   ├── Dashboard.vue       # Mastery + analytics
│   │   │   ├── MasteryMap.vue      # Radar chart deep dive
│   │   │   └── WeeklyReport.vue    # Weekly summary
│   │   │
│   │   ├── Gamification/
│   │   │   ├── Badges.vue
│   │   │   ├── Missions.vue
│   │   │   └── Leaderboard.vue
│   │   │
│   │   ├── Internship/
│   │   │   ├── Dashboard.vue       # Internship overview
│   │   │   ├── TaskBoard.vue       # Kanban board
│   │   │   ├── TaskDetail.vue      # Task + submission
│   │   │   ├── Submissions.vue     # Submission history
│   │   │   ├── Portfolio.vue       # Portfolio builder
│   │   │   └── Meetings.vue        # Meeting calendar
│   │   │
│   │   ├── Community/
│   │   │   ├── Forums.vue          # Forum listing
│   │   │   ├── Thread.vue          # Thread + replies
│   │   │   ├── Messages.vue        # Direct messages
│   │   │   └── StudyGroups.vue     # Study groups
│   │   │
│   │   ├── Storefront/
│   │   │   ├── Index.vue           # Product catalog
│   │   │   ├── Product.vue         # Product detail
│   │   │   └── Checkout.vue        # Payment flow
│   │   │
│   │   ├── Certificates/
│   │   │   ├── Index.vue           # My certificates
│   │   │   └── Verify.vue          # Public verification page
│   │   │
│   │   ├── Events/
│   │   │   ├── Index.vue           # Event calendar
│   │   │   └── Show.vue            # Event detail
│   │   │
│   │   ├── Pathways/
│   │   │   ├── Index.vue           # Career pathways
│   │   │   └── Show.vue            # Pathway detail
│   │   │
│   │   ├── Profile/
│   │   │   ├── Edit.vue            # Edit profile
│   │   │   ├── Preferences.vue     # Notification + accessibility prefs
│   │   │   └── Orders.vue          # Order history
│   │   │
│   │   ├── Notifications/
│   │   │   └── Index.vue           # Notification center
│   │   │
│   │   ├── Offline/
│   │   │   └── Downloads.vue       # Download manager
│   │   │
│   │   ├── Admin/
│   │   │   ├── Dashboard.vue
│   │   │   ├── Users/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Show.vue
│   │   │   │   └── Import.vue
│   │   │   ├── Courses/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Create.vue
│   │   │   │   └── Edit.vue
│   │   │   ├── Modules/
│   │   │   │   └── Edit.vue
│   │   │   ├── Lessons/
│   │   │   │   ├── Create.vue
│   │   │   │   └── Edit.vue
│   │   │   ├── Questions/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Create.vue
│   │   │   │   └── Edit.vue
│   │   │   ├── Organizations/
│   │   │   │   ├── Index.vue
│   │   │   │   └── Show.vue
│   │   │   ├── Programs/
│   │   │   │   └── Assign.vue
│   │   │   ├── Reports/
│   │   │   │   ├── Executive.vue
│   │   │   │   ├── Compliance.vue
│   │   │   │   └── Internship.vue
│   │   │   ├── Settings/
│   │   │   │   └── Index.vue
│   │   │   ├── AuditLogs/
│   │   │   │   └── Index.vue
│   │   │   └── Billing/
│   │   │       └── Index.vue
│   │   │
│   │   ├── Facilitator/
│   │   │   ├── Dashboard.vue
│   │   │   ├── Cohorts/
│   │   │   │   ├── Index.vue
│   │   │   │   ├── Health.vue
│   │   │   │   └── PlanBuilder.vue
│   │   │   ├── Learners/
│   │   │   │   └── Profile360.vue
│   │   │   ├── Assignments/
│   │   │   │   ├── Create.vue
│   │   │   │   └── Grading.vue
│   │   │   ├── LiveSessions/
│   │   │   │   └── Index.vue
│   │   │   ├── Interventions/
│   │   │   │   └── Index.vue
│   │   │   └── InternshipOversight/
│   │   │       └── Index.vue
│   │   │
│   │   ├── Sponsor/
│   │   │   ├── Dashboard.vue
│   │   │   ├── ReviewQueue.vue
│   │   │   ├── SubmissionReview.vue
│   │   │   ├── Scorecard.vue
│   │   │   └── MeetingHub.vue
│   │   │
│   │   └── SME/
│   │       ├── Dashboard.vue
│   │       ├── ContentReview.vue
│   │       ├── QuestionBank.vue
│   │       ├── ItemAnalytics.vue
│   │       ├── FlagQueue.vue
│   │       └── Versioning.vue
│   │
│   ├── Components/                 # Reusable components
│   │   ├── Common/
│   │   │   ├── AppButton.vue
│   │   │   ├── AppCard.vue
│   │   │   ├── AppModal.vue
│   │   │   ├── AppDropdown.vue
│   │   │   ├── AppTable.vue
│   │   │   ├── AppPagination.vue
│   │   │   ├── AppBadge.vue
│   │   │   ├── AppAlert.vue
│   │   │   ├── AppTabs.vue
│   │   │   ├── AppToast.vue
│   │   │   ├── AppSkeleton.vue
│   │   │   ├── AppAvatar.vue
│   │   │   ├── AppEmptyState.vue
│   │   │   ├── AppSearchInput.vue
│   │   │   ├── AppFileUpload.vue
│   │   │   ├── AppRichTextEditor.vue
│   │   │   └── AppConfirmDialog.vue
│   │   │
│   │   ├── Navigation/
│   │   │   ├── Sidebar.vue
│   │   │   ├── TopBar.vue
│   │   │   ├── BottomNav.vue        # Mobile bottom navigation
│   │   │   ├── Breadcrumbs.vue
│   │   │   └── NotificationBell.vue
│   │   │
│   │   ├── Course/
│   │   │   ├── CourseCard.vue
│   │   │   ├── ModuleAccordion.vue
│   │   │   ├── LessonNav.vue
│   │   │   ├── VideoPlayer.vue
│   │   │   ├── AudioPlayer.vue
│   │   │   ├── PdfViewer.vue
│   │   │   ├── ReadContent.vue
│   │   │   ├── ProgressBar.vue
│   │   │   └── DownloadButton.vue
│   │   │
│   │   ├── Quiz/
│   │   │   ├── QuestionCard.vue
│   │   │   ├── OptionsList.vue
│   │   │   ├── QuizTimer.vue
│   │   │   ├── QuizNavGrid.vue
│   │   │   ├── ConfidenceRater.vue
│   │   │   ├── ExplanationCard.vue
│   │   │   ├── ScoreSummary.vue
│   │   │   └── DomainBreakdown.vue
│   │   │
│   │   ├── Analytics/
│   │   │   ├── RadarChart.vue       # Mastery map
│   │   │   ├── LineChart.vue        # Trends
│   │   │   ├── BarChart.vue         # Activity
│   │   │   ├── Heatmap.vue          # Cohort heatmaps
│   │   │   ├── ReadinessMeter.vue   # Circular gauge
│   │   │   └── ErrorPatternCard.vue
│   │   │
│   │   ├── Gamification/
│   │   │   ├── XpBar.vue
│   │   │   ├── StreakCounter.vue
│   │   │   ├── BadgeGrid.vue
│   │   │   ├── MissionCard.vue
│   │   │   ├── LevelUpModal.vue
│   │   │   └── CelebrationAnimation.vue
│   │   │
│   │   ├── Internship/
│   │   │   ├── KanbanBoard.vue
│   │   │   ├── TaskCard.vue
│   │   │   ├── SubmissionForm.vue
│   │   │   ├── EvidenceUploader.vue
│   │   │   ├── RubricScorer.vue
│   │   │   ├── SponsorFeedback.vue
│   │   │   ├── MilestoneTracker.vue
│   │   │   └── PortfolioCard.vue
│   │   │
│   │   ├── Simulation/
│   │   │   ├── StoryNode.vue
│   │   │   ├── DecisionNode.vue
│   │   │   ├── ConsequenceNode.vue
│   │   │   ├── SimScorecard.vue
│   │   │   └── PathComparison.vue
│   │   │
│   │   ├── Community/
│   │   │   ├── ThreadCard.vue
│   │   │   ├── ReplyForm.vue
│   │   │   ├── MessageBubble.vue
│   │   │   └── UserPresence.vue
│   │   │
│   │   ├── Meeting/
│   │   │   ├── MeetingCard.vue
│   │   │   ├── CalendarView.vue
│   │   │   ├── AgendaEditor.vue
│   │   │   └── AttendanceList.vue
│   │   │
│   │   └── Flashcard/
│   │       ├── FlashcardFlip.vue
│   │       ├── QualityRater.vue
│   │       └── DeckProgress.vue
│   │
│   ├── Composables/                 # Vue 3 Composition API utilities
│   │   ├── useAuth.js               # Auth state + helpers
│   │   ├── usePermissions.js        # RBAC checks
│   │   ├── useNotifications.js      # Notification handling
│   │   ├── useOffline.js            # Offline detection + sync
│   │   ├── useTimer.js              # Quiz/exam timer logic
│   │   ├── useFileUpload.js         # File upload with progress
│   │   ├── useDebounce.js           # Debounced search
│   │   ├── usePagination.js         # Pagination helpers
│   │   ├── useToast.js              # Toast notifications
│   │   ├── useMediaQuery.js         # Responsive breakpoints
│   │   ├── useLocalStorage.js       # Persistent local state
│   │   └── useDragDrop.js           # Drag and drop (task board)
│   │
│   ├── Stores/                      # Pinia stores
│   │   ├── auth.js                  # User + token state
│   │   ├── course.js                # Current course/lesson state
│   │   ├── quiz.js                  # Active quiz state + answers
│   │   ├── gamification.js          # XP, streak, badges
│   │   ├── notifications.js         # Notification list + count
│   │   ├── internship.js            # Internship state
│   │   ├── offline.js               # Offline queue + sync status
│   │   └── ui.js                    # Sidebar state, modals, theme
│   │
│   └── Utils/
│       ├── api.js                   # Axios instance + interceptors
│       ├── formatters.js            # Date, currency, duration formatters
│       ├── validators.js            # Form validation rules
│       ├── constants.js             # App-wide constants
│       └── helpers.js               # Misc helpers
│
├── css/
│   ├── app.css                      # Tailwind imports + custom styles
│   ├── themes/
│   │   ├── default.css
│   │   ├── high-contrast.css
│   │   └── dyslexia.css
│   └── components/
│       └── animations.css           # Celebration, transitions
│
├── views/
│   └── app.blade.php                # Single Blade entry (Inertia root)
│
└── sw.js                            # Service Worker for PWA + offline
```

---

## 3. Design System

### 3.1 Color Palette

```
Primary:       #1E40AF (Blue 700)     — Brand primary
Primary Light: #3B82F6 (Blue 500)     — Hover/accent
Primary Dark:  #1E3A8A (Blue 800)     — Text on light
Secondary:     #F59E0B (Amber 500)    — Highlights, XP, streaks
Success:       #10B981 (Emerald 500)  — Correct, passed, approved
Warning:       #F59E0B (Amber 500)    — At risk, caution
Danger:        #EF4444 (Red 500)      — Wrong, failed, overdue
Info:          #3B82F6 (Blue 500)     — Information
Background:    #F8FAFC (Slate 50)     — Page background
Surface:       #FFFFFF                 — Card/component surface
Text Primary:  #1E293B (Slate 800)    — Body text
Text Secondary:#64748B (Slate 500)    — Supporting text
Border:        #E2E8F0 (Slate 200)    — Borders, dividers
```

### 3.2 Typography

```
Font Family:   Inter (default), OpenDyslexic (accessibility)
Heading 1:     30px / 700 weight
Heading 2:     24px / 700 weight
Heading 3:     20px / 600 weight
Heading 4:     18px / 600 weight
Body:          16px / 400 weight
Body Small:    14px / 400 weight
Caption:       12px / 400 weight
```

### 3.3 Spacing Scale

```
xs:  4px
sm:  8px
md:  16px
lg:  24px
xl:  32px
2xl: 48px
3xl: 64px
```

### 3.4 Breakpoints (Mobile-first)

```
sm:   640px   (landscape phones)
md:   768px   (tablets)
lg:  1024px   (small laptops)
xl:  1280px   (desktops)
2xl: 1536px   (large screens)
```

### 3.5 Component Patterns

**Cards:** Rounded-lg (8px), shadow-sm, white background, padding 16-24px
**Buttons:** Rounded-md, 44px min height (touch target), 3 sizes (sm/md/lg)
**Inputs:** Rounded-md, border-gray-300, focus:ring-primary, 44px height
**Modals:** Centered overlay, max-width 600px, rounded-xl
**Toasts:** Bottom-right, auto-dismiss 5s, color-coded by type

---

## 4. Navigation Architecture

### 4.1 Learner Navigation

**Mobile (bottom nav, 5 items):**
```
Home | Courses | Practice | Progress | More
```

**More menu expands:**
- Internship, Community, Events, Profile, Offline Downloads, Help

**Desktop (sidebar):**
```
Dashboard
Courses
Practice
  └ Quizzes
  └ Mock Exams
  └ Simulations
  └ Flashcards
Analytics
  └ Mastery Map
  └ Weekly Report
Internship
  └ Dashboard
  └ Task Board
  └ Portfolio
  └ Meetings
Community
  └ Forums
  └ Messages
  └ Study Groups
Events
Pathways
Storefront
──────────
Profile
Settings
Downloads
Help
```

### 4.2 Facilitator Navigation (Desktop sidebar)

```
Dashboard
Cohorts
  └ [Cohort Name]
    └ Health
    └ Plan Builder
    └ Learners
    └ Assignments
    └ Live Sessions
Grading
Interventions
Internship Oversight
Reports
Messages
```

### 4.3 Admin Navigation (Desktop sidebar)

```
Dashboard
Users & Teams
  └ User List
  └ Import
  └ Departments
Organizations
Courses
  └ Course Manager
  └ Categories
Programs
  └ Assignments
  └ Cohorts
  └ Internships
Commerce
  └ Orders
  └ Products
  └ Discount Codes
Reports
  └ Executive
  └ Compliance
  └ Departments
  └ Internship
Settings
  └ General
  └ Notifications
  └ Billing
  └ Privacy
Audit Logs
```

### 4.4 Sponsor Navigation

```
Dashboard
Review Queue
Intern Progress
Scorecards
Meeting Hub
```

### 4.5 SME Navigation

```
Dashboard
Content Library
Question Bank
Review Queue
Flag Queue
Item Analytics
Versioning
```

---

## 5. State Management (Pinia Stores)

### 5.1 Auth Store
```javascript
// stores/auth.js
{
  state: {
    user: null,              // Current user object
    token: null,             // Sanctum token (for API calls outside Inertia)
    permissions: [],         // Resolved permissions array
    isAuthenticated: false,
    isLoading: false
  },
  getters: {
    hasRole(role),
    hasPermission(perm),
    isAdmin,
    isFacilitator,
    isSponsor,
    isSME
  },
  actions: {
    login(credentials),
    logout(),
    fetchUser(),
    updateProfile(data)
  }
}
```

### 5.2 Quiz Store
```javascript
// stores/quiz.js
{
  state: {
    attempt: null,           // Current attempt object
    questions: [],           // Loaded questions
    currentIndex: 0,         // Current question index
    answers: {},             // { questionId: { selected, confidence, time } }
    flagged: [],             // Flagged question IDs
    timeRemaining: null,     // Seconds remaining (if timed)
    isPaused: false,
    isSubmitting: false
  },
  getters: {
    currentQuestion,
    answeredCount,
    flaggedCount,
    progress,
    isComplete
  },
  actions: {
    startAttempt(quizId),
    answerQuestion(questionId, answer),
    flagQuestion(questionId),
    navigateTo(index),
    pause(),
    resume(),
    submit()
  }
}
```

### 5.3 Offline Store
```javascript
// stores/offline.js
{
  state: {
    isOnline: true,
    syncQueue: [],           // Actions queued offline
    downloadedContent: {},   // { courseId: { lessons: [...], quizzes: [...] } }
    storageUsed: 0,          // Bytes
    lastSyncAt: null
  },
  actions: {
    queueAction(action),
    syncAll(),
    downloadCourse(courseId),
    downloadLesson(lessonId),
    removeDownload(courseId),
    checkConnectivity()
  }
}
```

---

## 6. PWA Configuration

### 6.1 Manifest
```json
{
  "name": "JK Michaels Learning",
  "short_name": "JKM Learn",
  "description": "Professional Mastery Platform",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#F8FAFC",
  "theme_color": "#1E40AF",
  "orientation": "portrait-primary",
  "icons": [
    { "src": "/icons/icon-72.png", "sizes": "72x72", "type": "image/png" },
    { "src": "/icons/icon-96.png", "sizes": "96x96", "type": "image/png" },
    { "src": "/icons/icon-128.png", "sizes": "128x128", "type": "image/png" },
    { "src": "/icons/icon-144.png", "sizes": "144x144", "type": "image/png" },
    { "src": "/icons/icon-152.png", "sizes": "152x152", "type": "image/png" },
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-384.png", "sizes": "384x384", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

### 6.2 Service Worker Strategy
- **App Shell:** Cache-first for static assets (CSS, JS, fonts, icons)
- **API Data:** Network-first with stale-while-revalidate fallback
- **Media:** Cache on demand (when user downloads)
- **Offline page:** Custom offline fallback page

### 6.3 Push Notifications
- Web Push API via service worker
- VAPID keys for server-side push
- Permission prompt on first relevant interaction (not on page load)
- Notification categories for granular control

---

## 7. Capacitor Configuration

### 7.1 Plugins Required
```
@capacitor/app           # App lifecycle
@capacitor/browser       # In-app browser (payment redirects)
@capacitor/camera         # Evidence photo capture
@capacitor/filesystem     # Offline file storage
@capacitor/local-notifications  # Local reminders
@capacitor/push-notifications   # FCM/APNs push
@capacitor/network        # Online/offline detection
@capacitor/status-bar     # Status bar styling
@capacitor/splash-screen  # Splash screen
@capacitor/share          # Share certificates/portfolio
@capacitor/haptics        # Subtle haptic feedback
```

### 7.2 Platform-specific Features

**Android:**
- Target SDK 34+
- Material You theming support
- Edge-to-edge display
- Back gesture handling

**iOS:**
- Safe area insets handling
- Swipe back navigation
- Haptic feedback on interactions

### 7.3 Capacitor Config
```typescript
// capacitor.config.ts
{
  appId: 'com.jkmichaels.learning',
  appName: 'JK Michaels Learning',
  webDir: 'public',
  server: {
    url: 'https://app.jkmichaels.com',  // Production
    cleartext: false
  },
  plugins: {
    SplashScreen: {
      launchShowDuration: 2000,
      backgroundColor: '#1E40AF'
    },
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert']
    }
  }
}
```

---

## 8. Charts & Visualization

Using **Chart.js** (via vue-chartjs wrapper):
- Radar chart: Mastery map
- Line chart: Readiness trend, score trends
- Bar chart: Daily activity, domain scores
- Doughnut chart: Completion progress, submission status
- Custom: Heatmap (grid component), circular gauge (readiness meter)

---

## 9. Key Page Wireframes (Structural)

### 9.1 Home Screen (Mobile)
```
┌─────────────────────────┐
│  Good morning, Toyin  🔔 │
├─────────────────────────┤
│  TODAY'S PLAN            │
│  ┌─────┐┌─────┐┌─────┐  │
│  │Quiz ││Recap││Sim  │  │
│  │10min││5min ││10min│  │
│  └─────┘└─────┘└─────┘  │
├─────────────────────────┤
│  READINESS     ⓘ        │
│     ╭───╮                │
│     │72%│  Likely to pass│
│     ╰───╯  14 days left  │
├─────────────────────────┤
│  🔥 12 day streak │ 450XP│
│  ▓▓▓▓▓▓▓░░ Lvl 5        │
│  Next: Focus Badge (2d)  │
├─────────────────────────┤
│  FOCUS AREAS             │
│  Risk Mgmt    42% ↑     │
│  Procurement  55% →     │
│  Scheduling   61% ↑     │
├─────────────────────────┤
│  CONTINUE                │
│  ┌──────────────────┐    │
│  │ ▶ Module 3, L5   │    │
│  │   Progress: 60%  │    │
│  └──────────────────┘    │
├─────────────────────────┤
│  💡 Coach: "Focus on     │
│  time management for     │  
│  scenario questions"     │
├─────────────────────────┤
│ 🏠  📚  📝  📊  ≡     │
│ Home Course Quiz Prog More│
└─────────────────────────┘
```

### 9.2 Lesson Page (Mobile)
```
┌─────────────────────────┐
│  ← Module 3, Lesson 5   │
│  Risk Response Planning  │
├─────────────────────────┤
│ Read │Watch│Listen│PDF│✏️│
├─────────────────────────┤
│                          │
│  [CONTENT AREA]          │
│                          │
│  Displays based on       │
│  selected tab:           │
│  - Rich text             │
│  - Video player          │
│  - Audio player          │
│  - PDF viewer            │
│  - Practice quiz         │
│                          │
├─────────────────────────┤
│  KEY TERMS               │
│  • Avoid: ...            │
│  • Transfer: ...         │
│  • Mitigate: ...         │
├─────────────────────────┤
│  ← Previous    Next →    │
│  ▓▓▓▓▓▓▓░░░ 5/8          │
└─────────────────────────┘
```

### 9.3 Quiz Player (Mobile)
```
┌─────────────────────────┐
│  Q 5 of 20    ⏱ 12:34   │
├─────────────────────────┤
│                          │
│  A project manager       │
│  discovers that a key    │
│  stakeholder has not     │
│  been included in...     │
│                          │
│  ○ A) Escalate to the   │
│       project sponsor    │
│                          │
│  ● B) Update the        │
│       stakeholder        │
│       register           │
│                          │
│  ○ C) Schedule a        │
│       meeting with the   │
│       stakeholder        │
│                          │
│  ○ D) Review the        │
│       communications     │
│       management plan    │
│                          │
├─────────────────────────┤
│  Confidence: 😐 Medium  │
├─────────────────────────┤
│  🚩Flag  ← Prev  Next → │
│  ○○○○● ○○○○○ ○○○○○ ○○○○○│
└─────────────────────────┘
```

### 9.4 Task Board (Mobile — Internship)
```
┌─────────────────────────┐
│  ← Task Board      + ▼  │
├─────────────────────────┤
│ Today│This Week│Review│+ │
├─────────────────────────┤
│  TODAY (3)               │
│  ┌──────────────────┐    │
│  │📋 Update risk reg │    │
│  │   Daily │ Due today│   │
│  │   🟡 In Progress   │   │
│  └──────────────────┘    │
│  ┌──────────────────┐    │
│  │📋 Team standup    │    │
│  │   Daily │ Due today│   │
│  │   ⬜ To Do         │   │
│  └──────────────────┘    │
│  ┌──────────────────┐    │
│  │📋 Log work hours  │    │
│  │   Daily │ Due today│   │
│  │   ⬜ To Do         │   │
│  └──────────────────┘    │
├─────────────────────────┤
│  IN REVIEW (1)           │
│  ┌──────────────────┐    │
│  │📋 WBS Deliverable │    │
│  │   Weekly │ Feb 24  │   │
│  │   🔵 Awaiting Review│  │
│  └──────────────────┘    │
└─────────────────────────┘
```

---

## 10. Performance Optimization

### 10.1 Code Splitting
- Route-level lazy loading via dynamic imports
- Heavy components (VideoPlayer, PdfViewer, Charts) lazy loaded
- Admin/Facilitator/Sponsor modules loaded only for authorized users

### 10.2 Image Optimization
- All images served as WebP with PNG fallback
- Responsive images via `srcset`
- Thumbnails: 300px wide
- Lazy loading for below-the-fold images

### 10.3 Caching Strategy (Frontend)
- Vuex/Pinia state persisted to localStorage for offline
- API responses cached with TTL (5 min for dashboard, 30 min for course list)
- Service worker caches static assets

### 10.4 Bundle Size Budget
- Initial load JS: < 200KB gzipped
- Largest chunk: < 100KB gzipped
- CSS: < 50KB gzipped
- Total first-paint resources: < 400KB

---

## 11. Testing Strategy

### 11.1 Unit Tests
- **Vitest** for Vue component testing
- Test all composables
- Test Pinia stores
- Test utility functions

### 11.2 Component Tests
- **Vue Testing Library** for component interaction tests
- Test forms, navigation, conditional rendering

### 11.3 E2E Tests
- **Cypress** or **Playwright** for critical user flows:
  - Registration → Onboarding → First lesson → Quiz
  - Payment → Enrollment → Course access
  - Internship submission → Sponsor review
  - Admin user management

### 11.4 Accessibility Tests
- **axe-core** integration with Cypress
- Lighthouse CI accessibility audit
- Manual screen reader testing (NVDA)

---

## 12. Internationalization (i18n) — Future Ready

- All user-facing strings extracted to locale files
- Vue I18n plugin configured
- Default locale: `en`
- Future locales: `fr` (Francophone Africa)
- RTL support structure in CSS (not implemented initially)
- Number and date formatting via `Intl` API
