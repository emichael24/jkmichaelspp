import { LayoutDashboard, BookOpen, Users, ClipboardCheck, FileQuestion, BarChart3, Settings, ShieldCheck, CalendarDays, BriefcaseBusiness, MessageSquare, ShoppingBag, Award, Layers, FolderOpen, GraduationCap, Sparkles, Target, HeartHandshake, CreditCard, Bell, Download, Bookmark, GitBranch, ListChecks, Building2 } from 'lucide-vue-next';

const item = (label, href, icon) => ({ label, href, icon });
export const workspaces = {
    learner: {
        label: 'Learning workspace', short: 'Learn', home: '/my-courses', role: null,
        groups: [
            { label: 'Learning', items: [item('My learning', '/my-courses', BookOpen), item('Course catalog', '/courses', FolderOpen), item('Practice & revision', '/practice', Target), item('Mock exams', '/mock-exams', FileQuestion), item('My progress', '/analytics', BarChart3), item('AI study tutor', '/ai/tutor', Sparkles)] },
            { label: 'Experience', items: [item('My cohorts', '/my-cohorts', Users), item('Internship', '/internship', BriefcaseBusiness), item('Simulations', '/simulations', GitBranch), item('Career pathways', '/pathways', GraduationCap), item('Community', '/community', MessageSquare), item('Events', '/events', CalendarDays)] },
            { label: 'My library', items: [item('Certificates', '/certificates', Award), item('Portfolio', '/portfolio', FolderOpen), item('Saved lessons', '/bookmarks', Bookmark), item('Downloads', '/downloads', Download), item('Store', '/storefront', ShoppingBag), item('Orders', '/orders', CreditCard), item('Subscriptions', '/subscriptions', Layers)] },
        ],
    },
    admin: {
        label: 'Administration', short: 'Admin', home: '/admin', role: 'platform_admin',
        groups: [
            { label: 'Learning operations', items: [item('Overview', '/admin', LayoutDashboard), item('Courses', '/admin/courses', BookOpen), item('Learning prerequisites', '/admin/learning-rules', GitBranch), item('Course approvals', '/admin/course-approvals', ClipboardCheck), item('Question bank', '/admin/questions', FileQuestion), item('Quizzes', '/admin/quizzes', ListChecks), item('Mock exams', '/admin/mock-exams', Target), item('Categories', '/admin/categories', FolderOpen), item('Internships', '/admin/internships', BriefcaseBusiness), item('Simulations', '/admin/simulations', GitBranch)] },
            { label: 'People & business', items: [item('Users & access', '/admin/users', Users), item('Organizations', '/admin/organizations', Building2), item('Reports', '/admin/reports', BarChart3), item('Events', '/admin/events', CalendarDays), item('Billing', '/admin/billing', CreditCard), item('Subscription plans', '/admin/subscriptions/plans', Layers), item('CRM overview', '/admin/erp', HeartHandshake), item('Contacts', '/admin/erp/contacts', Users), item('Deals', '/admin/erp/deals', BriefcaseBusiness), item('Campaigns', '/admin/erp/messaging', MessageSquare)] },
            { label: 'Governance', items: [item('AI insights', '/admin/erp/ai', Sparkles), item('Audit trail', '/admin/audit-logs', ShieldCheck), item('Platform settings', '/admin/settings', Settings), item('Launch checklist', '/admin/launch-checklist', ClipboardCheck)] },
        ],
    },
    facilitator: {
        label: 'Facilitator workspace', short: 'Teach', home: '/facilitator', role: 'facilitator',
        groups: [
            { label: 'Teaching', items: [item('Overview', '/facilitator', LayoutDashboard), item('My courses', '/facilitator/courses', BookOpen), item('Cohorts & gradebooks', '/facilitator/cohorts', Users), item('Grading queue', '/facilitator/grading', ClipboardCheck), item('Content studio', '/facilitator/ai-studio', Sparkles)] },
            { label: 'Internship oversight', items: [item('Intern health', '/facilitator/internship-oversight/health', BriefcaseBusiness), item('Portfolios', '/facilitator/internship-oversight/portfolios', FolderOpen)] },
        ],
    },
    sme: {
        label: 'Content quality', short: 'Review', home: '/sme/dashboard', role: 'sme_reviewer',
        groups: [
            { label: 'Content governance', items: [item('Overview', '/sme/dashboard', LayoutDashboard), item('Content library', '/sme', BookOpen), item('Review queue', '/sme/pending', ClipboardCheck), item('Question bank', '/sme/questions', FileQuestion), item('Flagged questions', '/sme/flags', ShieldCheck)] },
            { label: 'Quality insights', items: [item('Item analytics', '/sme/questions/analytics-dashboard', BarChart3), item('Blueprint coverage', '/sme/questions/blueprint-coverage', Target)] },
        ],
    },
    sponsor: {
        label: 'Sponsor workspace', short: 'Sponsor', home: '/sponsor', role: 'sponsor',
        groups: [{ label: 'Mentoring', items: [item('Overview', '/sponsor', LayoutDashboard), item('Review queue', '/sponsor/review-queue', ClipboardCheck), item('My interns', '/sponsor/interns', Users), item('Scorecards', '/sponsor/scorecards', ListChecks), item('Meetings', '/sponsor/meetings', CalendarDays)] }],
    },
    organization: {
        label: 'Organization workspace', short: 'Organization', home: '/organization', role: 'org_admin',
        groups: [{ label: 'Organization', items: [item('Overview', '/organization', Building2), item('People & invitations', '/organization/people', Users), item('Departments', '/organization/departments', Layers), item('Courses & licenses', '/organization/courses', BookOpen), item('Company billing', '/organization/billing', CreditCard), item('Learning reports', '/organization/reports', BarChart3)] }],
    },
};

export const personalLinks = [item('Messages', '/messages', MessageSquare), item('Notifications', '/notifications', Bell), item('My profile', '/profile/edit', Settings)];
