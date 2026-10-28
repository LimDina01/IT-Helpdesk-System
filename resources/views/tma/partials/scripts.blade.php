<script>
function tmaApp() {
    return {
        loading: true,
        authState: 'unlinked', // 'unlinked', 'authenticated', 'web_login_required'
        hasPendingRequest: false,
        pendingEmployeeCode: '',
        telegramUser: {},
        currentUser: {},
        linkEmployeeCode: '',
        linkError: '',
        submittingLink: false,
        isBrowserDevMode: false,

        // Direct Web Login State
        webLoginIdentifier: '',
        webLoginPassword: '',
        webLoginError: '',
        loggingIn: false,

        // Image Lightbox State
        showLightbox: false,
        lightboxUrl: '',
        lightboxFilename: '',
        lightboxDownloadUrl: '',

        // Multi-View Navigation Stack ('home', 'category_select', 'new_ticket_form', 'tickets_list')
        currentView: 'home',
        categorySearchQuery: '',
        ticketSearchQuery: '',

        // Tabs & Tickets (Filtered by 'open', 'in_progress', 'resolved', 'all')
        currentTab: 'open',
        activeTickets: [],
        historyTickets: [],
        approvalTickets: [],
        approvedTickets: [],
        activeCount: 0,
        historyCount: 0,
        pendingApprovalsCount: 0,
        loadingTickets: false,
        loadingFormData: false,

        // UI Localization (i18n)
        locale: localStorage.getItem('cbvh_tma_locale') || 'en',

        toggleLocale() {
            this.locale = this.locale === 'en' ? 'km' : 'en';
            try {
                localStorage.setItem('cbvh_tma_locale', this.locale);
            } catch (e) {}
        },

        t(key, fallback = '') {
            const dict = window.tmaI18n || {};
            if (dict[this.locale] && dict[this.locale][key] !== undefined) {
                return dict[this.locale][key];
            }
            if (dict['en'] && dict['en'][key] !== undefined) {
                return dict['en'][key];
            }
            return fallback || key;
        },

        formatStatus(status) {
            if (!status) return '';
            const key = 'status_' + status.toLowerCase().replace(/\s+/g, '_');
            return this.t(key, status.replace(/_/g, ' '));
        },

        formatPriority(priority) {
            if (!priority) return '';
            const key = 'priority_' + priority.toLowerCase();
            return this.t(key, priority);
        },

        formatType(type) {
            if (type === 'service_request') return this.t('type_service_request', 'Service Request');
            if (type === 'incident') return this.t('type_incident', 'Incident');
            return type;
        },

        getCategoryName(cat) {
            if (!cat) return '';
            if (typeof cat === 'string') return cat;
            if (this.locale === 'km') {
                if (cat.name_km && cat.name_km.trim()) return cat.name_km.trim();
                const n = (cat.name || '').toLowerCase();
                if (n.includes('account') || n.includes('access')) return 'គណនី & សិទ្ធិចូលប្រព័ន្ធ';
                if (n.includes('pos') || n.includes('cashier')) return 'ប្រព័ន្ធ POS & កុងទ័រគិតលុយ';
                if (n.includes('hardware') || n.includes('equipment')) return 'កុំព្យូទ័រ & ឧបករណ៍បច្ចេកវិទ្យា';
                if (n.includes('software') || n.includes('application')) return 'កម្មវិធីកុំព្យូទ័រ & អាជ្ញាប័ណ្ណ';
                if (n.includes('email') || n.includes('collaboration')) return 'អ៊ីមែល & Microsoft 365';
                if (n.includes('network') || n.includes('connectivity') || n.includes('internet')) return 'ប្រព័ន្ធបណ្តាញ & អ៊ីនធឺណិត';
                if (n.includes('business') || n.includes('erp')) return 'ប្រព័ន្ធគ្រប់គ្រងអាជីវកម្ម (ERP)';
                if (n.includes('facility') || n.includes('cctv') || n.includes('phone') || n.includes('other')) return 'សេវា IT ផ្សេងៗ & បន្ទប់ប្រជុំ';
                if (n.includes('procurement') || n.includes('service request')) return 'សំណើស្នើសុំឧបករណ៍ & អាជ្ញាប័ណ្ណ';
            }
            return cat.name || '';
        },

        get isHod() {
            return !!(
                this.currentUser?.is_hod ||
                this.formData?.user?.is_hod ||
                this.currentUser?.employee_code === 'EMP-1007' ||
                (Array.isArray(this.currentUser?.roles) && this.currentUser.roles.some(r => ['hod', 'system_admin'].includes(r)))
            );
        },

        get greetingName() {
            if (!this.currentUser?.name) return 'Colleague';
            const parts = this.currentUser.name.trim().split(/\s+/);
            return parts[0] || this.currentUser.name;
        },

        get userDepartment() {
            return this.currentUser?.department || this.formData?.user?.department_name || '';
        },

        get userBranch() {
            return this.currentUser?.branch || this.formData?.user?.branch_name || '';
        },

        get openCount() {
            return (this.activeTickets || []).filter(t => ['pending_approval', 'submitted', 'assigned', 'clarifying', 'reopened'].includes(t.status)).length;
        },

        get inProgressCount() {
            return (this.activeTickets || []).filter(t => ['in_progress', 'blocked', 'pending_confirmation'].includes(t.status)).length;
        },

        get resolvedCount() {
            return this.historyCount || (this.historyTickets || []).length;
        },

        get allCount() {
            return this.openCount + this.inProgressCount + this.resolvedCount;
        },

        get openTickets() {
            const statuses = ['pending_approval', 'submitted', 'assigned', 'clarifying', 'reopened'];
            return (this.activeTickets || []).filter(t => statuses.includes(t.status));
        },

        get inProgressTickets() {
            const statuses = ['in_progress', 'blocked', 'pending_confirmation'];
            return (this.activeTickets || []).filter(t => statuses.includes(t.status));
        },

        get resolvedTickets() {
            return this.historyTickets || [];
        },

        get allTickets() {
            const list = [...(this.activeTickets || []), ...(this.historyTickets || [])];
            return list.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
        },

        get currentList() {
            if (this.currentTab === 'open') return this.openTickets;
            if (this.currentTab === 'in_progress') return this.inProgressTickets;
            if (this.currentTab === 'resolved' || this.currentTab === 'history') return this.resolvedTickets;
            if (this.currentTab === 'approvals') return this.approvalTickets;
            if (this.currentTab === 'active') return this.activeTickets;
            return this.allTickets;
        },

        filterTickets(list) {
            if (!this.ticketSearchQuery || !this.ticketSearchQuery.trim()) {
                return list || [];
            }
            const q = this.ticketSearchQuery.toLowerCase().trim();
            return (list || []).filter(t => {
                const code = (t.ticket_code || t.ticket_number || '').toLowerCase();
                const subj = (t.subject || '').toLowerCase();
                const desc = (t.description || '').toLowerCase();
                const cat = (t.category_name || t.category?.name || '').toLowerCase();
                const reqName = (t.requester_name || t.requester?.name || '').toLowerCase();
                const reqDept = (t.requester_dept || t.requester?.department || '').toLowerCase();
                const tech = (t.technician_name || t.assigned_to?.name || '').toLowerCase();
                const status = (t.status || '').toLowerCase();
                const formattedStatus = (this.formatStatus(t.status) || '').toLowerCase();
                const type = (t.ticket_type || '').toLowerCase();
                return code.includes(q) ||
                       subj.includes(q) ||
                       desc.includes(q) ||
                       cat.includes(q) ||
                       reqName.includes(q) ||
                       reqDept.includes(q) ||
                       tech.includes(q) ||
                       status.includes(q) ||
                       formattedStatus.includes(q) ||
                       type.includes(q);
            });
        },

        get filteredCurrentList() {
            return this.filterTickets(this.currentList);
        },

        get filteredApprovalTickets() {
            return this.filterTickets(this.approvalTickets);
        },

        get filteredApprovedTickets() {
            return this.filterTickets(this.approvedTickets);
        },

        get selectedParentCategory() {
            return (this.formData.categories || []).find(c => c.id == this.formParentCatId);
        },

        get filteredCategoryList() {
            const targetType = this.formTicketType === 'service_request' ? 'service_request' : 'incident_issue';
            const list = (this.formData.categories || []).filter(c => c.type === targetType && c.is_active !== false);
            if (!this.categorySearchQuery || !this.categorySearchQuery.trim()) {
                return list;
            }
            const q = this.categorySearchQuery.toLowerCase().trim();
            return list.filter(c => {
                const nameMatch = (c.name || '').toLowerCase().includes(q);
                const nameKmMatch = (c.name_km || '').toLowerCase().includes(q);
                const subMatch = (c.children || []).some(sub => (sub.name || '').toLowerCase().includes(q) || (sub.name_km || '').toLowerCase().includes(q));
                const subtitleMatch = this.getCategorySubtitle(c).toLowerCase().includes(q);
                return nameMatch || nameKmMatch || subMatch || subtitleMatch;
            });
        },

        // Detail Modal & Chat
        activeTicketModal: false,
        showTicketInfo: false,
        selectedTicket: null,
        showFullStepper: false,
        messages: [],
        replyText: '',
        replyFiles: [],
        sendingReply: false,
        replyError: '',
        isReplyFocused: false,
        isNearBottom: true,
        showScrollBottomBtn: false,
        hasNewIncomingMessage: false,
        showReopenPrompt: false,
        reopenReason: '',
        confirmingAction: false,
        chatPollTimer: null,
        listPollTimer: null,

        // New Ticket Form State
        newTicketModal: false,
        formTicketType: 'service_request', // 'incident' or 'service_request'
        formData: { branches: [], categories: [] },
        formBranchId: '',
        formParentCatId: '',
        filteredSubcategories: [],
        formSubCatId: '',
        formPriority: 'medium',
        formSubject: '',
        formDescription: '',
        formAttachments: [],
        submittingTicket: false,
        formError: '',

        // Approval Actions
        showApprovePrompt: false,
        showRejectPrompt: false,
        approvalComment: '',
        rejectionReason: '',
        actioningApproval: false,

        get filteredParentCategories() {
            const targetType = this.formTicketType === 'service_request' ? 'service_request' : 'incident_issue';
            return (this.formData.categories || []).filter(c => c.type === targetType);
        },

        startIncidentFlow() {
            this.formTicketType = 'incident';
            this.categorySearchQuery = '';
            this.formParentCatId = '';
            this.filteredSubcategories = [];
            this.formSubCatId = '';
            this.formSubject = '';
            this.formDescription = '';
            this.formAttachments = [];
            this.formError = '';
            this.currentView = 'category_select';
            this.updateBackButton();
            if (!this.formData?.categories?.length) this.loadFormData();
        },

        startServiceRequestFlow() {
            this.formTicketType = 'service_request';
            this.categorySearchQuery = '';
            this.formParentCatId = '';
            this.filteredSubcategories = [];
            this.formSubCatId = '';
            this.formSubject = '';
            this.formDescription = '';
            this.formAttachments = [];
            this.formError = '';
            this.currentView = 'category_select';
            this.updateBackButton();
            if (!this.formData?.categories?.length) this.loadFormData();
        },

        selectCategory(cat) {
            this.formParentCatId = cat.id;
            this.filteredSubcategories = cat.children || [];

            // Auto-set subcategory to "Other" / optional as requested
            const otherSub = (this.filteredSubcategories || []).find(sub =>
                sub.name.toLowerCase().includes('other') || sub.name.toLowerCase().includes('unlisted')
            );
            if (otherSub) {
                this.formSubCatId = otherSub.id;
            } else if (this.filteredSubcategories.length > 0) {
                this.formSubCatId = this.filteredSubcategories[0].id;
            } else {
                this.formSubCatId = cat.id;
            }

            this.formPriority = cat.default_priority || 'medium';
            this.currentView = 'new_ticket_form';
            this.updateBackButton();
        },

        scrollToTop() {
            window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
        },

        openTicketsListTab(tab = 'open') {
            this.currentTab = tab;
            this.ticketSearchQuery = '';
            this.currentView = 'tickets_list';
            this.updateBackButton();
            this.scrollToTop();
            this.$nextTick(() => this.scrollToTop());
            setTimeout(() => this.scrollToTop(), 40);
        },

        openApprovalsList() {
            this.currentTab = 'approvals';
            this.ticketSearchQuery = '';
            this.currentView = 'tickets_list';
            this.updateBackButton();
            this.scrollToTop();
            this.$nextTick(() => this.scrollToTop());
            setTimeout(() => this.scrollToTop(), 40);
        },

        removeTicketFile(idx) {
            this.formAttachments.splice(idx, 1);
        },

        getCategorySubtitle(cat) {
            if (!cat || !cat.name) return this.t('select_category_desc', 'Select to view options');
            const n = cat.name.toLowerCase();
            if (this.locale === 'km') {
                if (n.includes('account') || n.includes('access')) return 'បង្កើតគណនីថ្មី សិទ្ធិចូលប្រព័ន្ធ ឬប្តូរសិទ្ធិប្រើប្រាស់';
                if (n.includes('pos') || n.includes('cashier')) return 'ម៉ាស៊ីនព្រីនវិក្កយបត្រ ស្កេនបាកូដ ថតលុយ ឬស្ថានីយ POS';
                if (n.includes('hardware') || n.includes('equipment')) return 'កុំព្យូទ័រយួរដៃ លើតុ ម៉ាស៊ីនព្រីន និងឧបករណ៍បច្ចេកវិទ្យា';
                if (n.includes('software') || n.includes('application')) return 'ការដំឡើងកម្មវិធី ស្នើសុំអាជ្ញាប័ណ្ណ ឬការចូលប្រើប្រាស់';
                if (n.includes('email') || n.includes('collaboration')) return 'អ៊ីមែល Microsoft 365, Teams, SharePoint';
                if (n.includes('network') || n.includes('connectivity') || n.includes('internet')) return 'ប្រព័ន្ធបណ្តាញ Wi-Fi, VPN, និងអ៊ីនធឺណិត';
                if (n.includes('business') || n.includes('erp')) return 'ប្រព័ន្ធ Business Central, POS, RFID, Humica, IQ, Bio-Start';
                if (n.includes('facility') || n.includes('cctv') || n.includes('phone') || n.includes('other')) return 'បន្ទប់ប្រជុំ កាមេរ៉ាសុវត្ថិភាព ទូរស័ព្ទ និងសេវាផ្សេងៗ';
                if (n.includes('procurement') || n.includes('service')) return 'ស្នើសុំកុំព្យូទ័រថ្មី បរិក្ខារ IT និងការបញ្ជាក់លក្ខណៈបច្ចេកទេស';
                return 'ជ្រើសរើសប្រភេទសេវាកម្មដើម្បីបន្ត';
            }
            if (n.includes('account') || n.includes('access')) return 'New account, system access, permission change';
            if (n.includes('pos') || n.includes('cashier')) return 'Receipt printer, barcode scanner, cash drawer, terminal';
            if (n.includes('hardware') || n.includes('equipment')) return 'Laptop, desktop, printer, network device, etc.';
            if (n.includes('software') || n.includes('application')) return 'Software installation, license request, application access';
            if (n.includes('email') || n.includes('collaboration')) return 'Email, Microsoft 365, Teams, SharePoint';
            if (n.includes('network') || n.includes('connectivity') || n.includes('internet')) return 'Network, Wi-Fi, VPN, internet';
            if (n.includes('business') || n.includes('erp')) return 'Business Central, POS, RFID, Humica, IQ, Bio-Start, etc.';
            if (n.includes('facility') || n.includes('cctv') || n.includes('phone') || n.includes('other')) return 'Meeting room, CCTV, phone, others';
            if (n.includes('procurement') || n.includes('service')) return 'Hardware procurement, IT asset spec recommendation';
            return 'Select category and proceed';
        },

        getCategoryIconBg(cat) {
            const n = (cat?.name || '').toLowerCase();
            if (n.includes('email')) return 'bg-rose-500';
            if (n.includes('network') || n.includes('connectivity') || n.includes('internet')) return 'bg-emerald-500';
            if (n.includes('hardware') || n.includes('equipment')) return 'bg-sky-600';
            if (n.includes('software') || n.includes('application')) return 'bg-blue-500';
            if (n.includes('business') || n.includes('erp')) return 'bg-blue-600';
            if (n.includes('facility') || n.includes('cctv') || n.includes('other') || n.includes('phone')) return 'bg-amber-500';
            if (n.includes('pos') || n.includes('cashier')) return 'bg-sky-700';
            if (n.includes('procurement') || (n.includes('service') && !n.includes('hardware'))) return 'bg-indigo-600';
            return 'bg-blue-600';
        },

        initApp() {
            // Strictly enforce Solid Light Mode on iOS/Android regardless of phone theme
            const enforceLightMode = () => {
                try {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.style.colorScheme = 'light';
                    if (document.body) {
                        document.body.classList.remove('dark');
                        document.body.style.colorScheme = 'light';
                        document.body.style.backgroundColor = '#f8fafc';
                        document.body.style.color = '#0f172a';
                    }
                } catch (e) {}
            };
            enforceLightMode();

            // Scroll to the very top whenever the active view changes
            this.$watch('currentView', () => {
                this.scrollToTop();
                this.$nextTick(() => this.scrollToTop());
                setTimeout(() => this.scrollToTop(), 40);
            });

            const tg = window.Telegram?.WebApp;
            if (tg) {
                tg.ready();
                tg.expand();
                tg.enableClosingConfirmation();
                try {
                    tg.setHeaderColor('#ffffff');
                    tg.setBackgroundColor('#f8fafc');
                    tg.onEvent('themeChanged', () => {
                        enforceLightMode();
                        try {
                            tg.setHeaderColor('#ffffff');
                            tg.setBackgroundColor('#f8fafc');
                        } catch (e) {}
                    });
                } catch (e) {}

                // Hook up Telegram Native BackButton (for iPhone & Android)
                try {
                    if (tg.BackButton) {
                        tg.BackButton.onClick(() => {
                            this.handleBackButtonClick();
                        });
                    }
                } catch (e) {}
            }

            // Sync Telegram BackButton with active modals and view stack
            try {
                this.$watch('currentView', () => this.updateBackButton());
                this.$watch('activeTicketModal', () => this.updateBackButton());
                this.$watch('showTicketInfo', () => this.updateBackButton());
                this.$watch('showLightbox', () => this.updateBackButton());
            } catch (e) {}

            // Immediately hydrate cached user profile so name & department render in 0ms
            try {
                const cachedUser = localStorage.getItem('cbvh_tma_current_user');
                if (cachedUser) this.currentUser = JSON.parse(cachedUser);
            } catch (e) {}

            // Clean up any obsolete cached ticket keys that might have mixed active & history
            try {
                localStorage.removeItem('cbvh_tma_tickets');
            } catch (e) {}

            // Immediately hydrate cached categories/branches so dropdowns are available in 0ms
            try {
                const cachedForm = localStorage.getItem('cbvh_tma_form_data');
                if (cachedForm) {
                    const parsed = JSON.parse(cachedForm);
                    if (parsed.branches?.length && parsed.categories?.length) {
                        this.formData = parsed;
                        if (parsed.branches.length > 0) {
                            this.formBranchId = parsed.user?.branch_id || parsed.branches[0].id;
                        }
                    }
                }
            } catch (e) {}

            // Immediately hydrate cached tickets and counts so the UI renders instantly with 0ms delay!
            try {
                const cachedActive = localStorage.getItem('cbvh_tma_active_tickets');
                if (cachedActive) this.activeTickets = JSON.parse(cachedActive);

                const cachedHistory = localStorage.getItem('cbvh_tma_history_tickets');
                if (cachedHistory) this.historyTickets = JSON.parse(cachedHistory);

                const cachedCounts = localStorage.getItem('cbvh_tma_counts');
                if (cachedCounts) {
                    const parsedCounts = JSON.parse(cachedCounts);
                    this.activeCount = parsedCounts.active ?? this.activeTickets.length;
                    this.historyCount = parsedCounts.history ?? this.historyTickets.length;
                    this.pendingApprovalsCount = parsedCounts.approvals ?? 0;
                }
            } catch (e) {}

            // If user navigates away or refreshes while inside chat modal, clear presence immediately
            window.addEventListener('beforeunload', () => {
                if (this.selectedTicket?.id && this.activeTicketModal) {
                    const token = document.querySelector('meta[name="csrf-token"]')?.content;
                    const blob = new Blob([JSON.stringify({ _token: token })], { type: 'application/json' });
                    navigator.sendBeacon(`/tma/api/tickets/${this.selectedTicket.id}/leave`, blob);
                }
            });

            this.checkAuth();
            this.loadFormData();
            this.startListPolling();
        },

        startListPolling() {
            if (this.listPollTimer) clearInterval(this.listPollTimer);
            this.listPollTimer = setInterval(() => {
                if (!this.activeTicketModal && this.authState === 'authenticated') {
                    this.loadTickets(true);
                }
            }, 4000);
        },

        openLightbox(url, name, downloadUrl) {
            this.lightboxUrl = url;
            this.lightboxFilename = name;
            this.lightboxDownloadUrl = downloadUrl;
            this.showLightbox = true;
        },

        checkAuth() {
            this.loading = true;
            const initData = window.Telegram?.WebApp?.initData || '';

            // Check if opening in standard browser without Telegram
            if (!initData) {
                this.isBrowserDevMode = true;
            }

            fetch('/tma/api/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ init_data: initData })
            })
            .then(res => res.json())
            .then(data => {
                this.loading = false;
                if (data.status === 'authenticated') {
                    this.authState = 'authenticated';
                    this.currentUser = data.user;
                    try { localStorage.setItem('cbvh_tma_current_user', JSON.stringify(data.user)); } catch (e) {}
                    this.loadTickets();
                    this.loadFormData();
                } else if (data.status === 'unlinked') {
                    this.authState = 'unlinked';
                    this.hasPendingRequest = data.has_pending_request;
                    this.pendingEmployeeCode = data.pending_request?.employee_code || '';
                    this.telegramUser = data.telegram_user || {};
                } else if (data.status === 'web_login_required') {
                    this.authState = 'web_login_required';
                }
            })
            .catch(() => {
                this.loading = false;
                this.authState = 'web_login_required';
            });
        },

        submitWebLogin() {
            this.loggingIn = true;
            this.webLoginError = '';

            fetch('/tma/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    login: this.webLoginIdentifier,
                    password: this.webLoginPassword,
                    remember: true
                })
            })
            .then(async res => {
                const data = await res.json();
                this.loggingIn = false;
                if (!res.ok) {
                    this.webLoginError = data.error || 'Login failed. Please check credentials.';
                } else {
                    this.authState = 'authenticated';
                    this.currentUser = data.user;
                    try { localStorage.setItem('cbvh_tma_current_user', JSON.stringify(data.user)); } catch (e) {}
                    this.webLoginPassword = '';
                    this.loadTickets();
                    this.loadFormData();
                }
            })
            .catch(() => {
                this.loggingIn = false;
                this.webLoginError = 'Network error. Please try again.';
            });
        },

        logout() {
            fetch('/tma/api/logout', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            }).finally(() => {
                this.authState = 'web_login_required';
                this.currentUser = {};
                this.activeTickets = [];
                this.historyTickets = [];
                this.approvalTickets = [];
                this.activeCount = 0;
                this.historyCount = 0;
                this.pendingApprovalsCount = 0;
                try {
                    localStorage.removeItem('cbvh_tma_current_user');
                    localStorage.removeItem('cbvh_tma_active_tickets');
                    localStorage.removeItem('cbvh_tma_history_tickets');
                    localStorage.removeItem('cbvh_tma_counts');
                } catch (e) {}
            });
        },

        devLogin(empCode) {
            this.loading = true;
            fetch('/tma/api/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ dev_employee_code: empCode })
            })
            .then(res => res.json())
            .then(data => {
                this.loading = false;
                if (data.status === 'authenticated') {
                    this.authState = 'authenticated';
                    this.currentUser = data.user;
                    try { localStorage.setItem('cbvh_tma_current_user', JSON.stringify(data.user)); } catch (e) {}
                    this.loadTickets();
                    this.loadFormData();
                }
            });
        },

        submitLinkRequest() {
            this.submittingLink = true;
            this.linkError = '';

            const initData = window.Telegram?.WebApp?.initData || '';

            fetch('/tma/api/link-request', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    init_data: initData,
                    telegram_id: this.telegramUser.id || 123456789,
                    telegram_username: this.telegramUser.username || null,
                    first_name: this.telegramUser.first_name || '',
                    last_name: this.telegramUser.last_name || '',
                    employee_code: this.linkEmployeeCode
                })
            })
            .then(async res => {
                const data = await res.json();
                this.submittingLink = false;
                if (!res.ok) {
                    this.linkError = data.error || 'Failed to submit link request';
                } else {
                    this.hasPendingRequest = true;
                    this.pendingEmployeeCode = data.employee_code;
                }
            })
            .catch(() => {
                this.submittingLink = false;
                this.linkError = 'Network error. Please try again.';
            });
        },

        switchTab(tab) {
            this.currentTab = tab;
            // 0.0ms instant tab change! Active and History tickets are completely separated arrays.
        },

        loadTickets(silent = false) {
            if (!silent && this.activeTickets.length === 0 && this.historyTickets.length === 0) {
                this.loadingTickets = true;
            }
            fetch(`/tma/api/tickets?_t=${Date.now()}`, {
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            })
            .then(res => res.json())
            .then(data => {
                this.loadingTickets = false;
                this.activeTickets = data.active || [];
                this.historyTickets = data.history || [];
                this.approvalTickets = data.approvals || [];
                this.approvedTickets = data.approved_by_me || [];

                this.activeCount = data.active_count ?? this.activeTickets.length;
                this.historyCount = data.history_count ?? this.historyTickets.length;
                this.pendingApprovalsCount = data.pending_approvals_count ?? 0;

                if (data.is_hod && this.currentUser) {
                    this.currentUser.is_hod = true;
                }

                try {
                    localStorage.setItem('cbvh_tma_active_tickets', JSON.stringify(this.activeTickets));
                    localStorage.setItem('cbvh_tma_history_tickets', JSON.stringify(this.historyTickets));
                    localStorage.setItem('cbvh_tma_counts', JSON.stringify({
                        active: this.activeCount,
                        history: this.historyCount,
                        approvals: this.pendingApprovalsCount
                    }));
                } catch (e) {}
            })
            .catch(() => {
                this.loadingTickets = false;
            });
        },

        openTicketDetail(id) {
            fetch(`/tma/api/tickets/${id}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                this.selectedTicket = data.ticket;
                this.messages = data.messages || [];
                this.showApprovePrompt = false;
                this.showRejectPrompt = false;
                this.approvalComment = '';
                this.rejectionReason = '';
                this.showFullStepper = false;
                this.showTicketInfo = false;
                this.activeTicketModal = true;
                this.isNearBottom = true;
                this.showScrollBottomBtn = false;
                this.hasNewIncomingMessage = false;
                this.updateBackButton();
                this.scrollToBottom(true);
                this.startChatPolling(id);
            });
        },

        startChatPolling(id) {
            this.stopChatPolling();
            this.chatPollTimer = setInterval(() => {
                if (!this.activeTicketModal || !this.selectedTicket || this.selectedTicket.id !== id) {
                    this.stopChatPolling();
                    return;
                }

                fetch(`/tma/api/tickets/${id}?_t=${Date.now()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (!this.activeTicketModal || !this.selectedTicket) return;
                    const prevCount = this.messages ? this.messages.length : 0;
                    const newCount = data.messages ? data.messages.length : 0;
                    this.selectedTicket = data.ticket;
                    this.messages = data.messages || [];
                    if (newCount > prevCount) {
                        if (this.isNearBottom) {
                            this.scrollToBottom(true, true);
                        } else {
                            this.hasNewIncomingMessage = true;
                        }
                    }
                })
                .catch(() => {});
            }, 2500);
        },

        stopChatPolling() {
            if (this.chatPollTimer) {
                clearInterval(this.chatPollTimer);
                this.chatPollTimer = null;
            }
        },

        closeTicketModal() {
            const ticketId = this.selectedTicket ? this.selectedTicket.id : null;
            this.dismissKeyboard();
            this.stopChatPolling();
            this.showTicketInfo = false;
            this.activeTicketModal = false;
            this.selectedTicket = null;
            this.updateBackButton();
            this.replyText = '';
            this.replyFiles = [];
            this.showReopenPrompt = false;
            this.showApprovePrompt = false;
            this.showRejectPrompt = false;

            // Immediately clear server presence so Telegram alert triggers if IT sends a message
            if (ticketId) {
                fetch(`/tma/api/tickets/${ticketId}/leave`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                }).catch(() => {});
            }

            this.loadTickets(true);
        },

        handleReplyFiles(e) {
            this.replyFiles = Array.from(e.target.files);
            this.replyError = '';
            // Refocus text field so user can seamlessly continue typing
            this.$nextTick(() => {
                const input = document.getElementById('chatTextInput');
                if (input) {
                    input.focus();
                }
            });
        },

        removeReplyFile(i) {
            this.replyFiles.splice(i, 1);
            if (this.replyFiles.length === 0) {
                const fi = document.getElementById('chatFileInput');
                if (fi) fi.value = '';
                const pi = document.getElementById('chatPhotoInput');
                if (pi) pi.value = '';
            }
        },

        sendReply() {
            if (!this.selectedTicket || this.sendingReply) return;
            if (!this.replyText && this.replyFiles.length === 0) return;

            this.sendingReply = true;
            this.replyError = '';
            const formData = new FormData();
            if (this.replyText) formData.append('body', this.replyText);
            this.replyFiles.forEach(f => formData.append('attachments[]', f));

            fetch(`/tma/api/tickets/${this.selectedTicket.id}/messages`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(async res => {
                const data = await res.json();
                this.sendingReply = false;
                if (!res.ok) {
                    this.replyError = data.message || Object.values(data.errors || {})[0]?.[0] || 'Failed to send message';
                    if (window.Telegram?.WebApp?.showAlert) {
                        window.Telegram.WebApp.showAlert(this.replyError);
                    }
                } else {
                    this.replyText = '';
                    this.replyFiles = [];
                    this.replyError = '';
                    const fi = document.getElementById('chatFileInput');
                    if (fi) fi.value = '';
                    const pi = document.getElementById('chatPhotoInput');
                    if (pi) pi.value = '';
                    this.openTicketDetail(this.selectedTicket.id);
                }
            })
            .catch(() => {
                this.sendingReply = false;
                this.replyError = 'Network error while sending message. Please try again.';
                if (window.Telegram?.WebApp?.showAlert) {
                    window.Telegram.WebApp.showAlert(this.replyError);
                }
            });
        },

        submitApprove() {
            if (!this.selectedTicket || !this.selectedTicket.current_approval_step) return;
            this.actioningApproval = true;
            fetch(`/tma/api/approvals/${this.selectedTicket.current_approval_step.id}/approve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ comments: this.approvalComment })
            })
            .then(res => res.json())
            .then(() => {
                this.actioningApproval = false;
                this.showApprovePrompt = false;
                this.approvalComment = '';
                this.loadTickets(true);
                this.openTicketDetail(this.selectedTicket.id);
            })
            .catch(() => {
                this.actioningApproval = false;
            });
        },

        submitReject() {
            if (!this.selectedTicket || !this.selectedTicket.current_approval_step || !this.rejectionReason) return;
            this.actioningApproval = true;
            fetch(`/tma/api/approvals/${this.selectedTicket.current_approval_step.id}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ reason: this.rejectionReason })
            })
            .then(res => res.json())
            .then(() => {
                this.actioningApproval = false;
                this.showRejectPrompt = false;
                this.rejectionReason = '';
                this.loadTickets(true);
                this.openTicketDetail(this.selectedTicket.id);
            })
            .catch(() => {
                this.actioningApproval = false;
            });
        },

        confirmResolution() {
            if (!this.selectedTicket) return;
            this.confirmingAction = true;
            fetch(`/tma/api/tickets/${this.selectedTicket.id}/confirm`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(() => {
                this.confirmingAction = false;
                this.loadTickets(true);
                this.openTicketDetail(this.selectedTicket.id);
            });
        },

        submitReopen() {
            if (!this.selectedTicket || !this.reopenReason) return;
            fetch(`/tma/api/tickets/${this.selectedTicket.id}/reopen`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ reason: this.reopenReason })
            })
            .then(res => res.json())
            .then(() => {
                this.showReopenPrompt = false;
                this.reopenReason = '';
                this.loadTickets(true);
                this.openTicketDetail(this.selectedTicket.id);
            });
        },

        loadFormData() {
            this.loadingFormData = true;
            fetch('/tma/api/form-data?_t=' + Date.now(), {
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            })
            .then(res => res.json())
            .then(data => {
                this.loadingFormData = false;
                if (data.branches && data.categories) {
                    this.formData = data;
                    try {
                        localStorage.setItem('cbvh_tma_form_data', JSON.stringify(data));
                    } catch (e) {}

                    if (!this.formBranchId && data.branches.length > 0) {
                        this.formBranchId = data.user?.branch_id || data.branches[0].id;
                    }
                    if (data.user?.pending_approvals_count !== undefined) {
                        this.pendingApprovalsCount = data.user.pending_approvals_count;
                    }
                }
            })
            .catch(() => {
                this.loadingFormData = false;
            });
        },

        openNewTicketModal() {
            this.startServiceRequestFlow();
        },

        onParentCategoryChange() {
            const parent = this.formData.categories.find(c => c.id == this.formParentCatId);
            if (parent && parent.children) {
                this.filteredSubcategories = parent.children;
                if (parent.children.length > 0) {
                    this.formSubCatId = parent.children[0].id;
                }
                this.formPriority = parent.default_priority || 'medium';
            } else {
                this.filteredSubcategories = [];
                this.formSubCatId = '';
            }
        },

        handleNewTicketFiles(e) {
            this.formAttachments = Array.from(e.target.files);
        },

        submitNewTicket() {
            if (!this.formSubject || !this.formSubject.trim()) {
                this.formError = 'Please enter a title / subject.';
                return;
            }
            if (!this.formDescription || !this.formDescription.trim()) {
                this.formError = 'Please provide details of your request.';
                return;
            }

            this.submittingTicket = true;
            this.formError = '';

            const formData = new FormData();
            formData.append('ticket_type', this.formTicketType);
            const branchId = this.formBranchId || this.formData.user?.branch_id || (this.formData.branches?.[0]?.id);
            if (branchId) {
                formData.append('branch_id', branchId);
            }
            formData.append('category_id', this.formSubCatId || this.formParentCatId);
            formData.append('priority', this.formPriority || 'medium');
            formData.append('subject', this.formSubject);
            formData.append('description', this.formDescription);
            this.formAttachments.forEach(f => formData.append('attachments[]', f));

            fetch('/tma/api/tickets', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(async res => {
                const data = await res.json();
                this.submittingTicket = false;
                if (!res.ok) {
                    this.formError = data.message || 'Failed to submit ticket';
                } else {
                    this.dismissKeyboard();
                    this.formSubject = '';
                    this.formDescription = '';
                    this.formAttachments = [];
                    this.currentView = 'home';
                    this.updateBackButton();
                    this.loadTickets(true);
                    this.currentTab = 'open';
                    this.openTicketDetail(data.ticket_id);
                }
            })
            .catch(() => {
                this.submittingTicket = false;
                this.formError = 'Network error while submitting ticket.';
            });
        },

        getServiceStepClass(t, step) {
            if (!t) return 'bg-slate-200 text-slate-400';
            const stepApproval = (t.approvals || []).find(a => a.step_number === step);
            if (stepApproval) {
                if (stepApproval.status === 'approved') return 'bg-emerald-500 text-white';
                if (stepApproval.status === 'rejected') return 'bg-rose-100 text-rose-700 border border-rose-300 font-bold';
            }
            if (t.status === 'rejected' || t.status === 'cancelled') {
                return 'bg-slate-100 text-slate-400 border border-slate-200';
            }
            if (!t.is_pending_approval) return 'bg-emerald-500 text-white';
            const currentStepNum = t.current_approval_step?.step_number;
            if (currentStepNum > step) return 'bg-emerald-500 text-white';
            if (currentStepNum === step) return 'bg-blue-600 text-white ring-2 ring-blue-300 animate-pulse';
            return 'bg-slate-200 text-slate-400';
        },

        getServiceStepIcon(t, step) {
            if (!t) return step;
            const stepApproval = (t.approvals || []).find(a => a.step_number === step);
            if (stepApproval) {
                if (stepApproval.status === 'approved') return '✔';
                if (stepApproval.status === 'rejected') return '✕';
            }
            if (t.status === 'rejected' || t.status === 'cancelled') {
                return '—';
            }
            const currentStepNum = t.current_approval_step?.step_number;
            if (!t.is_pending_approval || currentStepNum > step) return '✔';
            return step;
        },

        getFulfillmentStepClass(t) {
            if (!t) return 'bg-slate-200 text-slate-400';
            if (['in_progress', 'pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(t.status)) return 'bg-emerald-500 text-white';
            if (t.status === 'submitted' || t.status === 'assigned') return 'bg-blue-600 text-white ring-2 ring-blue-300 animate-pulse';
            return 'bg-slate-200 text-slate-400';
        },

        getFulfillmentStepIcon(t) {
            if (!t) return '4';
            if (['in_progress', 'pending_confirmation', 'resolved_confirmed', 'resolved_auto_closed'].includes(t.status)) return '✔';
            return '4';
        },

        getStatusBannerClass(status) {
            const map = {
                'pending_approval': 'bg-amber-50 text-amber-900 border border-amber-200',
                'submitted': 'bg-blue-50 text-blue-900 border border-blue-200',
                'assigned': 'bg-purple-50 text-purple-900 border border-purple-200',
                'in_progress': 'bg-indigo-50 text-indigo-900 border border-indigo-200',
                'blocked': 'bg-red-50 text-red-900 border border-red-200',
                'pending_confirmation': 'bg-emerald-50 text-emerald-900 border border-emerald-300 font-bold',
                'resolved_confirmed': 'bg-slate-100 text-slate-800 border border-slate-200',
                'resolved_auto_closed': 'bg-slate-100 text-slate-800 border border-slate-200',
                'rejected': 'bg-rose-50 text-rose-900 border border-rose-200',
                'reopened': 'bg-rose-50 text-rose-900 border border-rose-200',
                'cancelled': 'bg-slate-100 text-slate-600 border border-slate-200'
            };
            return map[status] || 'bg-slate-50 text-slate-700 border border-slate-200';
        },

        getCurrentStageTitle(t) {
            if (!t) return '';
            if (t.status === 'pending_approval') return (t.current_approval_step?.step_name || 'Approval Required');
            if (t.status === 'submitted') return 'Approved & Received by IT';
            if (t.status === 'assigned') return 'Assigned to IT Technician';
            if (t.status === 'in_progress') return 'Work In Progress / Setup';
            if (t.status === 'blocked') return 'Procurement / Parts Hold';
            if (t.status === 'pending_confirmation') return 'Fulfillment Ready - Confirm Signoff';
            if (t.status === 'resolved_confirmed' || t.status === 'resolved_auto_closed') return 'Completed & Closed';
            if (t.status === 'rejected') {
                const rejStep = (t.approvals || []).find(a => a.status === 'rejected');
                if (rejStep) return 'Rejected at ' + rejStep.step_name;
                return 'Request Rejected';
            }
            return t.status;
        },

        getCurrentStageDetail(t) {
            if (!t) return '';
            if (t.status === 'pending_approval') {
                return 'Waiting for ' + (t.current_approval_step?.step_name || 'HOD') + ' review & signoff.';
            }
            if (t.status === 'submitted') return 'All approvals granted! Waiting for Help Desk triage & technician dispatch.';
            if (t.status === 'assigned') return 'Assigned to ' + t.technician_name + '. Awaiting technician acceptance.';
            if (t.status === 'in_progress') return t.technician_name + ' is actively configuring/procuring your request.';
            if (t.status === 'blocked') return 'Hold Reason: ' + (t.blocked_reason || 'Waiting for supplier/parts.');
            if (t.status === 'pending_confirmation') return 'IT completed the request. Please verify and confirm resolution above.';
            if (t.status === 'rejected') {
                const rejStep = (t.approvals || []).find(a => a.status === 'rejected');
                if (rejStep) {
                    const approver = rejStep.approver_name || rejStep.step_name;
                    const reason = rejStep.comments || t.cancellation_reason || 'Declined during review.';
                    return `Declined by ${approver}. Reason: ${reason}`;
                }
                return 'Reason: ' + (t.cancellation_reason || 'Declined during review.');
            }
            return 'Processing normal workflow.';
        },

        getCurrentStageEmoji(t) {
            if (!t) return '📋';
            if (t.status === 'pending_approval') return '⏳';
            if (t.status === 'submitted') return '📥';
            if (t.status === 'assigned') return '👤';
            if (t.status === 'in_progress') return '🛠';
            if (t.status === 'blocked') return '🛑';
            if (t.status === 'pending_confirmation') return '✨';
            if (t.status === 'resolved_confirmed') return '✔';
            if (t.status === 'rejected') return '❌';
            return '📋';
        },

        getIncidentStageTitle(t) {
            if (!t) return '';
            if (t.status === 'submitted') return 'Reported - In Triage';
            if (t.status === 'assigned') return 'Assigned to Technician';
            if (t.status === 'in_progress') return 'Technician Working';
            if (t.status === 'blocked') return 'Blocked / Waiting on Parts';
            if (t.status === 'clarifying') return 'IT Help Desk Needs Information';
            if (t.status === 'pending_confirmation') return 'Fix Completed - Verify Resolution';
            if (t.status === 'resolved_confirmed') return 'Resolved & Confirmed';
            if (t.status === 'reopened') return 'Reopened - Returned to Tech';
            return t.status;
        },

        getIncidentStageDetail(t) {
            if (!t) return '';
            if (t.status === 'submitted') return 'Waiting for IT Help Desk specialist to review & dispatch technician.';
            if (t.status === 'assigned') return 'Assigned to ' + t.technician_name + '. Tech will accept shortly.';
            if (t.status === 'in_progress') return t.technician_name + ' is actively troubleshooting the problem.';
            if (t.status === 'blocked') return 'Blocked: ' + (t.blocked_reason || 'Waiting on vendor or replacement.');
            if (t.status === 'clarifying') return 'Please read IT message below and submit your reply.';
            if (t.status === 'pending_confirmation') return 'Technician tested the fix. Please test and confirm above.';
            return 'Issue is being handled.';
        },

        getIncidentStageEmoji(t) {
            if (!t) return '⚡';
            if (t.status === 'submitted') return '📥';
            if (t.status === 'assigned') return '👤';
            if (t.status === 'in_progress') return '🛠';
            if (t.status === 'blocked') return '🛑';
            if (t.status === 'pending_confirmation') return '✨';
            if (t.status === 'resolved_confirmed') return '✔';
            return '⚡';
        },

        getStatusBadgeClass(status) {
            const map = {
                'pending_approval': 'bg-blue-50 text-blue-800 border border-blue-300 font-bold',
                'submitted': 'bg-blue-100 text-blue-800 font-semibold',
                'clarifying': 'bg-amber-100 text-amber-900 border border-amber-300',
                'assigned': 'bg-blue-50 text-blue-700 border border-blue-200',
                'in_progress': 'bg-amber-100 text-amber-900 border border-amber-300 font-bold',
                'blocked': 'bg-amber-100 text-amber-800 border border-amber-300',
                'pending_confirmation': 'bg-amber-100 text-amber-900 border border-amber-400 font-bold',
                'resolved_confirmed': 'bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold',
                'resolved_auto_closed': 'bg-emerald-50 text-emerald-700 border border-emerald-100',
                'reopened': 'bg-blue-100 text-blue-800 border border-blue-300 font-bold',
                'rejected': 'bg-rose-100 text-rose-800 border border-rose-300',
                'cancelled': 'bg-slate-100 text-slate-400'
            };
            return map[status] || 'bg-slate-100 text-slate-700';
        },

        formatStatus(status) {
            const map = {
                'pending_approval': 'Pending Approval',
                'submitted': 'Triage',
                'clarifying': 'Needs Info',
                'assigned': 'Assigned',
                'in_progress': 'In Progress',
                'blocked': 'Blocked',
                'pending_confirmation': 'Verify Fix',
                'resolved_confirmed': 'Completed',
                'resolved_auto_closed': 'Auto Closed',
                'reopened': 'Reopened',
                'rejected': 'Rejected',
                'cancelled': 'Cancelled'
            };
            return map[status] || status;
        },

        scrollToBottom(force = false, smooth = false) {
            // If user is scrolled up reading chat history and this wasn't an explicit force, never interrupt them
            if (!force && !this.isNearBottom) {
                return;
            }
            const doScroll = () => {
                const c = document.getElementById('timelineContainer');
                if (c) {
                    if (smooth) {
                        c.scrollTo({ top: c.scrollHeight + 10000, behavior: 'smooth' });
                    } else {
                        c.scrollTop = c.scrollHeight + 10000;
                    }
                    this.isNearBottom = true;
                    this.showScrollBottomBtn = false;
                    this.hasNewIncomingMessage = false;
                }
            };
            this.$nextTick(doScroll);
            if (force) {
                setTimeout(doScroll, 40);
                setTimeout(doScroll, 120);
                setTimeout(doScroll, 300);
            }
        },

        onTimelineScroll(e) {
            const c = e.target;
            const dist = c.scrollHeight - c.scrollTop - c.clientHeight;
            this.isNearBottom = dist < 120;
            this.showScrollBottomBtn = dist > 240;
            if (this.isNearBottom) {
                this.hasNewIncomingMessage = false;
            }
        },

        isNewDay(idx) {
            if (idx === 0) return true;
            const prev = this.messages[idx - 1];
            const curr = this.messages[idx];
            if (!prev || !curr || !prev.created_at || !curr.created_at) return false;
            return prev.created_at.split(' ')[0] !== curr.created_at.split(' ')[0];
        },

        shouldShowTimeSeparator(idx) {
            if (idx === 0) return false;
            const prev = this.messages[idx - 1];
            const curr = this.messages[idx];
            if (!prev || !curr || !prev.created_at || !curr.created_at) return false;
            if (this.isNewDay(idx)) return false; // Date separator already displays
            try {
                const parse = (str) => {
                    const [d, t] = str.split(' ');
                    if (!d || !t) return 0;
                    const [day, mon, yr] = d.split('-');
                    const months = {Jan:0,Feb:1,Mar:2,Apr:3,May:4,Jun:5,Jul:6,Aug:7,Sep:8,Oct:9,Nov:10,Dec:11};
                    const [hr, min] = t.split(':');
                    return new Date(yr, months[mon] ?? 0, day, hr, min).getTime();
                };
                const diff = parse(curr.created_at) - parse(prev.created_at);
                return diff > 20 * 60 * 1000;
            } catch (e) {
                return false;
            }
        },

        getDayLabel(msg) {
            if (!msg || !msg.created_at) return '';
            const datePart = msg.created_at.split(' ')[0]; // '26-Oct-2026'
            const today = new Date();
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const pad = (n) => String(n).padStart(2, '0');
            const todayStr = `${pad(today.getDate())}-${months[today.getMonth()]}-${today.getFullYear()}`;
            
            const yesterday = new Date();
            yesterday.setDate(today.getDate() - 1);
            const yesterdayStr = `${pad(yesterday.getDate())}-${months[yesterday.getMonth()]}-${yesterday.getFullYear()}`;

            if (datePart === todayStr) return 'Today';
            if (datePart === yesterdayStr) return 'Yesterday';
            return datePart.replace(/-/g, ' ');
        },

        formatBubbleTime(msg) {
            if (!msg || !msg.created_at) return '';
            const parts = msg.created_at.split(' ');
            if (parts.length > 1) {
                const timePart = parts[1]; // e.g. "13:05" or "04:39"
                const [hStr, mStr] = timePart.split(':');
                if (hStr !== undefined && mStr !== undefined) {
                    let h = parseInt(hStr, 10);
                    const ampm = h >= 12 ? 'PM' : 'AM';
                    h = h % 12;
                    h = h ? h : 12;
                    return `${h}:${mStr} ${ampm}`;
                }
                return timePart;
            }
            return msg.created_at;
        },

        formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + sizes[i];
        },

        openLightbox(url, filename, downloadUrl) {
            this.lightboxUrl = url;
            this.lightboxFilename = filename || 'Image Preview';
            this.lightboxDownloadUrl = downloadUrl || url;
            this.showLightbox = true;
        },

        getFileExtension(filename) {
            if (!filename) return 'FILE';
            const parts = filename.split('.');
            const ext = parts.length > 1 ? parts.pop() : '';
            return ext ? ext.slice(0, 4).toUpperCase() : 'FILE';
        },

        getFileBadgeClass(filename, isMe) {
            const ext = (filename || '').split('.').pop().toLowerCase();
            if (['pdf'].includes(ext)) return 'bg-rose-500 text-white';
            if (['doc', 'docx'].includes(ext)) return 'bg-blue-600 text-white';
            if (['xls', 'xlsx', 'csv'].includes(ext)) return 'bg-emerald-600 text-white';
            if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return 'bg-amber-600 text-white';
            return isMe ? 'bg-blue-800 text-white' : 'bg-slate-700 text-white';
        },

        touchStartY: 0,

        isMobileDevice() {
            try {
                const tgPlatform = window.Telegram?.WebApp?.platform;
                if (tgPlatform) {
                    return ['ios', 'android'].includes(tgPlatform);
                }
                return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || window.matchMedia('(hover: none) and (pointer: coarse)').matches;
            } catch (e) {
                return false;
            }
        },

        handleTimelineTouchStart(e) {
            if (e.touches && e.touches.length > 0) {
                this.touchStartY = e.touches[0].clientY;
            }
        },

        handleTimelineTouchMove(e) {
            // Only on mobile touch devices when an input is actively focused
            if (!this.isMobileDevice()) return;
            if (document.activeElement && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                if (e.touches && e.touches.length > 0) {
                    const deltaY = e.touches[0].clientY - this.touchStartY;
                    // User actively dragging downwards on messages -> dismiss keyboard
                    if (deltaY > 35) {
                        this.dismissKeyboard();
                    }
                }
            }
        },

        handleTimelineClick(e) {
            if (!this.isMobileDevice()) return;
            if (document.activeElement && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                if (!e.target.closest('input, textarea, button, a, select, label, img')) {
                    this.dismissKeyboard();
                }
            }
        },

        dismissKeyboard() {
            this.isReplyFocused = false;
            if (document.activeElement && typeof document.activeElement.blur === 'function') {
                document.activeElement.blur();
            }
        },

        updateBackButton() {
            const tg = window.Telegram?.WebApp;
            if (!tg?.BackButton) return;
            try {
                if (this.showLightbox || this.showTicketInfo || this.activeTicketModal || this.currentView !== 'home') {
                    tg.BackButton.show();
                } else {
                    tg.BackButton.hide();
                }
            } catch (e) {}
        },

        handleBackButtonClick() {
            // 1. If an input or textarea is currently focused, hide keyboard first
            if (document.activeElement && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                this.dismissKeyboard();
                return;
            }
            // 2. If lightbox is open, close lightbox
            if (this.showLightbox) {
                this.showLightbox = false;
                this.updateBackButton();
                return;
            }
            // 3. If ticket info / details page is open, return back to chat
            if (this.showTicketInfo) {
                this.showTicketInfo = false;
                this.updateBackButton();
                return;
            }
            // 4. If approval/rejection/reopen prompt is open, close prompt
            if (this.showApprovePrompt || this.showRejectPrompt || this.showReopenPrompt) {
                this.showApprovePrompt = false;
                this.showRejectPrompt = false;
                this.showReopenPrompt = false;
                return;
            }
            // 5. If ticket detail chat modal is open, close it
            if (this.activeTicketModal) {
                this.closeTicketModal();
                this.updateBackButton();
                return;
            }
            // 6. Multi-View navigation stack transitions
            if (this.currentView === 'new_ticket_form') {
                this.currentView = 'category_select';
                this.updateBackButton();
                return;
            }
            if (this.currentView === 'category_select' || this.currentView === 'tickets_list') {
                this.currentView = 'home';
                this.updateBackButton();
                return;
            }
        },

        copyTicketCode(code) {
            if (!code) return;
            try {
                navigator.clipboard.writeText(code);
                if (window.Telegram?.WebApp?.showPopup) {
                    window.Telegram.WebApp.showPopup({
                        title: 'Copied',
                        message: `Ticket code ${code} copied to clipboard!`,
                        buttons: [{ type: 'ok' }]
                    });
                }
            } catch (e) {}
        }
    }
}
</script>
