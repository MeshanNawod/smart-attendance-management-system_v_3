/* ─── RATINGS, FORUM, TOOLTIPS & i18n ────────────────────────── */

/* Local escape function (falls back to window.escapeHtml) */
function esc(t) {
  if (typeof window.escapeHtml === 'function') return window.escapeHtml(t);
  if (!t) return '-';
  return String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

/* Local CSRF getter (falls back to dashboard's getCsrf or main.js getCsrfToken) */
function getCsrf() {
  if (typeof window.getCsrfToken === 'function') return window.getCsrfToken();
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

/* ═══════════════════════════════════════════════════════════════
   I18N — Multi-Language Support (Sinhala / Tamil / English)
   ═══════════════════════════════════════════════════════════════ */
const I18N = {
  en: {
    lang_name: 'English',
    loading: 'Loading...',
    no_data: 'No data available',
    error: 'Error',
    success: 'Success',
    save: 'Save',
    cancel: 'Cancel',
    delete: 'Delete',
    edit: 'Edit',
    reply: 'Reply',
    like: 'Like',
    unlike: 'Unlike',
    pin: 'Pin',
    unpin: 'Unpin',
    delete_comment: 'Delete Comment',
    edit_comment: 'Edit Comment',
    comment_placeholder: 'Write a comment...',
    reply_placeholder: 'Write a reply...',
    submit_comment: 'Post Comment',
    submit_reply: 'Post Reply',
    no_comments: 'No comments yet. Be the first!',
    rating_remove: 'Remove Rating',
    rating_confirm: 'Are you sure?',
    yes: 'Yes',
    no: 'No',
    overall_rating: 'Overall Rating',
    out_of_5: 'out of 5',
    based_on: 'based on',
    reviews: 'reviews',
    your_rating: 'Your Rating',
    rate_teacher: 'Rate this Teacher',
    rate_course: 'Rate this Course',
    rate_session: 'Rate this Session',
    attendance: 'Attendance',
    enrolled: 'Enrolled',
    not_enrolled: 'Not Enrolled',
    course: 'Course',
    teacher: 'Teacher',
    student: 'Student',
    session: 'Session',
    comments: 'Comments',
    ratings: 'Ratings',
    forum: 'Forum',
    courses: 'Courses',
    history: 'History',
    overall: 'Overall',
    tier: 'Tier',
    settings: 'Settings',
    help: 'Help',
    dashboard: 'Dashboard',
    sign_out: 'Sign Out',
    welcome: 'Welcome',
    search: 'Search',
    filter: 'Filter',
    all: 'All',
    today: 'Today',
    this_week: 'This Week',
    this_month: 'This Month',
    students_base: 'Students Base',
    teachers: 'Teachers',
    lectures_export: 'Lectures & Export',
    hardware_nodes: 'Hardware Nodes',
    sign_in: 'Sign In',
    sentinel_swarm: 'Sentinel Swarm',
    brand_sub: 'AMS \u00B7 University of Peradeniya',
    exit: 'Exit',
    node_ip: 'Node IP',
    esp32_online: 'ESP32 ONLINE',
    esp32_offline: 'ESP32 OFFLINE',
    rpi_online: 'RPi ONLINE',
    rpi_offline: 'RPi OFFLINE',
    student_portal: 'Student Portal',
    teacher_portal: 'Teacher Portal',
    total_students: 'Total Students',
    system_clock: 'System Clock',
    today_attendance: "Today's Attendance",
    live_logs: 'Live Attendance Logs',
    quick_actions: 'Quick Actions',
    recent_activity: 'Recent Activity',
    uptime: 'Uptime',
    last_seen: 'Last Seen',
    minutes_ago: 'min ago',
    seconds_ago: 'sec ago',
    password: 'Password',
    username_or_id: 'Username or Student ID',
    enter_password: 'Enter your password',
    student_login_hint: 'Students: your initial password is your Student ID.',
    contact_admin: 'Contact your administrator if you cannot log in.',
    fields_required: 'Please enter both your username and password.',
    signing_in: 'Signing in...',
    account_locked: 'Account Temporarily Locked',
    lock_message: 'Too many failed attempts.',
    please_wait: 'Please wait',
    seconds: 'second(s)',
    token_mismatch: 'Token mismatch.',
    invalid_credentials: 'Invalid credentials.',
    attempts_remaining: 'attempt(s) remaining.',
    session_expired: 'Session expired. Please log in again.',
    security_violation: 'Security violation detected.',
    absent_students: 'Absent Students',
    attendance_percentage: 'Attendance %',
    no_students: 'No students found',
    start_lecture: 'Start Lecture',
    end_lecture: 'End Lecture',
    mark_present: 'Mark Present',
    export_csv: 'Export CSV',
    edit_profile: 'Edit Profile',
    set_password: 'Set Password',
    first_time_login: 'First-Time Login',
    you_must_change_password: 'You must change your password.',
    new_password: 'New Password',
    confirm_password: 'Confirm Password',
    cancel_and_logout: 'Cancel and Logout',
    change_password: 'Change Password',
    attendance_logs: 'Attendance Logs',
    my_courses: 'My Courses',
    enroll_students: 'Enroll Students',
    ratings_forum: 'Ratings & Forum',
    enroll_student: 'Enroll Student',
    attendance_charts: 'Attendance Charts',
    ratings_feedback: 'Ratings & Feedback',
    forum_discussion: 'Forum Discussion',
    daily_trend: 'Daily Attendance Trend',
    modality_split: 'Modality Split',
    course_distribution: 'Per-Course Attendance',
    hourly_pattern: 'Hourly Pattern',
    course_ratings: 'Course Ratings',
    recent_comments: 'Recent Comments',
    course_discussion: 'Course Discussion',
    mute: 'Mute',
    all_devices: 'All Devices',
    search_student: 'Search student...',
    loading_live_logs: 'Loading live logs...',
    no_logs_found: 'No logs found based on current filters.',
    total_enrolled: 'Total Enrolled',
    registered_students: 'Registered students',
    checkins_today: 'Check-ins today',
    system_time: 'System Time',
    include_columns: 'Include Columns',
    date_from: 'Date From',
    date_to: 'Date To',
    time_from: 'Time From',
    time_to: 'Time To',
    device: 'Device',
    course: 'Course',
    student_no: 'Student No',
    student_name: 'Name',
    timestamp: 'Timestamp',
    modality: 'Modality',
    ip_address: 'IP',
    offline_sync: 'Offline Sync',
    log_id: 'Log ID',
    cancel: 'Cancel',
    no_ratings: 'No ratings yet.',
    no_comments_yet: 'No comments yet.',
    select_course: 'Select a course...',
    loading_ratings: 'Loading ratings...',
    loading_comments: 'Loading comments...',
    no_discussion: 'No discussion yet for this course.',
    write_comment: 'Write a comment...',
    entries: 'entries',
    days_7: '7 days',
    days_14: '14 days',
    days_30: '30 days',
    days_90: '90 days',
    post: 'Post',
    all_courses: 'All Courses',
    web_dashboard: 'Web Dashboard',
    leave_blank_all: 'Leave blank for all',
    export: 'Export',
    section_history: 'Section History',
    export_current_view: 'Export Current View',
    advanced_export: 'Advanced Export',
    clear_all: 'Clear All',
    download_selected: 'Download Selected',
    selected: 'selected',
    no_sections: 'No completed sections yet.',
    section: 'Section',
    start: 'Start',
    end: 'End',
    duration: 'Duration',
    download_csv: 'Download CSV',
    clear_section_history: 'Clear all section history?',
    total_attendance: 'Total Attendance',
    today: 'Today',
    students: 'Students',
    courses: 'Courses',
    individual_analysis: 'Individual Analysis',
    per_student: 'Per Student',
    per_course: 'Per Course',
    per_teacher: 'Per Teacher',
    course_trend: 'Course Trend',
    student_trend: 'Student Trend',
  },
  si: {
    lang_name: 'සිංහල',
    loading: 'පූරණය වෙමින්...',
    no_data: 'දත්ත නැත',
    error: 'දෝෂය',
    success: 'සාර්ථකයි',
    save: 'සුරකින්න',
    cancel: 'අවලංගු කරන්න',
    delete: 'මකන්න',
    edit: 'සංස්කරණය',
    reply: 'පිළිතුරු',
    like: 'කැමතියි',
    unlike: 'අකමැතියි',
    pin: 'ඇමිණීම',
    unpin: 'ඇමිණීම ඉවත් කරන්න',
    delete_comment: 'අදහස මකන්න',
    edit_comment: 'අදහස සංස්කරණය',
    comment_placeholder: 'අදහසක් ලියන්න...',
    reply_placeholder: 'පිළිතුරක් ලියන්න...',
    submit_comment: 'අදහස පළ කරන්න',
    submit_reply: 'පිළිතුර පළ කරන්න',
    no_comments: 'තවම අදහස් නැත. පළමුවැන්නා වන්න!',
    overall_rating: 'සමස්ත ඇගයීම',
    out_of_5: 'න් 5',
    based_on: 'පදනම්ව',
    reviews: 'සමාලෝචන',
    your_rating: 'ඔබේ ඇගයීම',
    rate_teacher: 'ගුරුවරයා ඇගයීමට ලක් කරන්න',
    rate_course: 'පාඨමාලාව ඇගයීමට ලක් කරන්න',
    attendance: 'පැමිණීම',
    enrolled: 'ලියාපදිංචි',
    not_enrolled: 'ලියාපදිංචි නැත',
    course: 'පාඨමාලාව',
    teacher: 'ගුරුවරයා',
    student: 'ශිෂ්‍ය',
    session: 'සැසිය',
    comments: 'අදහස්',
    ratings: 'ඇගයීම්',
    forum: 'සංසදය',
    courses: 'පාඨමාලා',
    history: 'ඉතිහාසය',
    overall: 'සමස්ත',
    tier: 'ස්ථරය',
    settings: 'සැකසුම්',
    help: 'උදව්',
    dashboard: 'උපකරණ පුවරුව',
    sign_out: 'පිටවීම',
    welcome: 'ආයුබෝවන්',
    search: 'සොයන්න',
    filter: 'පෙරහන්',
    all: 'සියල්ල',
    today: 'අද',
    this_week: 'මෙම සතිය',
    this_month: 'මෙම මාසය',
    students_base: 'සිසු පදනම',
    teachers: 'ගුරුවරු',
    lectures_export: 'දේශන සහ අපනයන',
    hardware_nodes: 'දෘඩාංග නෝඩ්',
    sign_in: 'පිවිසෙන්න',
    sentinel_swarm: 'සෙන්ටිනල් ස්වෝර්ම්',
    brand_sub: 'AMS \u00B7 පේරාදෙණිය විශ්වවිද්\u200dයාලය',
    exit: 'පිටවීම',
    node_ip: 'නෝඩ් IP',
    esp32_online: 'ESP32 සබැඳි',
    esp32_offline: 'ESP32 නොබැඳි',
    rpi_online: 'RPi සබැඳි',
    rpi_offline: 'RPi නොබැඳි',
    student_portal: 'ශිෂ්\u200dය ද්වාරය',
    teacher_portal: 'ගුරු ද්වාරය',
    total_students: 'මුළු සිසුන්',
    system_clock: 'පද්ධති ඔරලෝසුව',
    today_attendance: 'අද පැමිණීම',
    live_logs: 'සජීවී පැමිණීම් ලොග්',
    quick_actions: 'ඉක්මන් ක්‍රියා',
    recent_activity: 'මෑත ක්‍රියාකාරකම්',
    uptime: 'ක්‍රියාත්මක කාලය',
    last_seen: 'අවසන් වරට දුටු',
    minutes_ago: 'මිනි පෙර',
    seconds_ago: 'තත් පෙර',
    password: 'මුරපදය',
    username_or_id: 'පරිශීලක නම හෝ ශිෂ්‍ය අංකය',
    enter_password: 'ඔබගේ මුරපදය ඇතුළත් කරන්න',
    student_login_hint: 'සිසුන්: ඔබගේ ආරම්භක මුරපදය ඔබගේ ශිෂ්‍ය අංකයයි.',
    contact_admin: 'ප්‍රවේශ විය නොහැකි නම් පරිපාලක අමතන්න.',
    fields_required: 'කරුණාකර ඔබගේ පරිශීලක නම සහ මුරපදය ඇතුළත් කරන්න.',
    signing_in: 'ඇතුළු වෙමින්...',
    account_locked: 'ගිණුම තාවකාලිකව අගුළු දමා ඇත',
    lock_message: 'බොහෝ අසාර්ථක උත්සහයන්.',
    please_wait: 'කරුණාකර රැඳී සිටින්න',
    seconds: 'තත්පර',
    token_mismatch: 'ටෝකන් ගැලපීමක් නැත.',
    invalid_credentials: 'වලංගු නොවන අක්තපත්‍ර.',
    attempts_remaining: 'උත්සහය(න්) ඉතිරිය.',
    session_expired: 'සැසිය කල් ඉකුත් විය. කරුණාකර නැවත පුරනය වන්න.',
    security_violation: 'ආරක්ෂක උල්ලංඝනයක් හමු විය.',
    absent_students: 'නොපැමිණි සිසුන්',
    attendance_percentage: 'පැමිණීමේ ප්‍රතිශතය',
    no_students: 'සිසුන් හමු නොවීය',
    start_lecture: 'දේශනය ආරම්භ කරන්න',
    end_lecture: 'දේශනය අවසන් කරන්න',
    mark_present: 'පැමිණ සිටි ලෙස සලකුණු කරන්න',
    export_csv: 'CSV අපනයනය',
    edit_profile: 'පැතිකඩ සංස්කරණය',
    set_password: 'මුරපදය සකසන්න',
    first_time_login: 'පළමු වරට පුරනය වීම',
    you_must_change_password: 'ඔබ ඔබගේ මුරපදය වෙනස් කළ යුතුය.',
    new_password: 'නව මුරපදය',
    confirm_password: 'මුරපදය තහවුරු කරන්න',
    cancel_and_logout: 'අවලංගු කර පිටවන්න',
    change_password: 'මුරපදය වෙනස් කරන්න',
    attendance_logs: 'පැමිණීම් ලොග්',
    my_courses: 'මගේ පාඨමාලා',
    enroll_students: 'සිසුන් ඇතුළත් කරන්න',
    ratings_forum: 'ඇගයීම් සහ සංසදය',
    enroll_student: 'සිසුවා ඇතුළත් කරන්න',
    attendance_charts: 'පැමිණීම් ප්‍රස්ථාර',
    ratings_feedback: 'ඇගයීම් සහ ප්‍රතිචාර',
    forum_discussion: 'සංසද සාකච්ඡාව',
    daily_trend: 'දෛනික පැමිණීම් ප්‍රවණතාව',
    modality_split: 'ක්‍රම බෙදීම',
    course_distribution: 'පාඨමාලා අනුව පැමිණීම',
    hourly_pattern: 'පැය ගණනින් රටාව',
    course_ratings: 'පාඨමාලා ඇගයීම්',
    recent_comments: 'මෑත අදහස්',
    course_discussion: 'පාඨමාලා සාකච්ඡාව',
    mute: 'නිහඬ කරන්න',
    all_devices: 'සියලුම උපාංග',
    search_student: 'සිසුන් සොයන්න...',
    loading_live_logs: 'සජීවී ලොග් පූරණය වෙමින්...',
    no_logs_found: 'පෙරහන් අනුව ලොග් හමු නොවීය.',
    total_enrolled: 'සම්පූර්ණ ඇතුළත් කිරීම්',
    registered_students: 'ලියාපදිංචි සිසුන්',
    checkins_today: 'අද පැමිණීම්',
    system_time: 'පද්ධති වේලාව',
    include_columns: 'තීරු ඇතුළත් කරන්න',
    date_from: 'දිනය සිට',
    date_to: 'දිනය දක්වා',
    time_from: 'වේලාව සිට',
    time_to: 'වේලාව දක්වා',
    device: 'උපාංගය',
    course: 'පාඨමාලාව',
    student_no: 'සිසු අංකය',
    student_name: 'නම',
    timestamp: 'වේලා මුද්‍රාව',
    modality: 'ක්‍රමය',
    ip_address: 'IP ලිපිනය',
    offline_sync: 'නොබැඳි සමමුහුර්ත',
    log_id: 'ලොග් ID',
    no_ratings: 'තවම ඇගයීම් නොමැත.',
    no_comments_yet: 'තවම අදහස් නොමැත.',
    select_course: 'පාඨමාලාවක් තෝරන්න...',
    loading_ratings: 'ඇගයීම් පූරණය වෙමින්...',
    loading_comments: 'අදහස් පූරණය වෙමින්...',
    no_discussion: 'මෙම පාඨමාලාව සඳහා තවම සාකච්ඡා නොමැත.',
    write_comment: 'අදහසක් ලියන්න...',
    entries: 'ප්‍රවේශ',
    days_7: 'දින 7',
    days_14: 'දින 14',
    days_30: 'දින 30',
    days_90: 'දින 90',
    post: 'පළ කරන්න',
    all_courses: 'සියලුම පාඨමාලා',
    web_dashboard: 'වෙබ් උපකරණ පුවරුව',
    leave_blank_all: 'සියල්ල සඳහා හිස් තබන්න',
    export: 'අපනයනය',
    section_history: 'කොටස් ඉතිහාසය',
    export_current_view: 'වත්මන් දසුන අපනයනය කරන්න',
    advanced_export: 'උසස් අපනයනය',
    clear_all: 'සියල්ල මකන්න',
    download_selected: 'තෝරාගත් බාගන්න',
    selected: 'තෝරාගත්',
    no_sections: 'තවත් සම්පූර්ණ කළ කොටස් නොමැත.',
    section: 'කොටස',
    start: 'ආරම්භය',
    end: 'අවසානය',
    duration: 'කාලය',
    download_csv: 'CSV බාගන්න',
    clear_section_history: 'සියලු කොටස් ඉතිහාසය මකන්නද?',
    total_attendance: 'මුළු පැමණීම',
    today: 'අද',
    students: 'සිසුන්',
    courses: 'පාඨමාලා',
    individual_analysis: 'තනි පුද්ගල විශ්ලේෂණය',
    per_student: 'සිසුවෙකු අනුව',
    per_course: 'පාඨමාලාවක් අනුව',
    per_teacher: 'ගුරුවරයෙකු අනුව',
    course_trend: 'පාඨමාලා ප්රවණතාව',
    student_trend: 'සිසු ප්රවණතාව',
  },
  ta: {
    lang_name: 'தமிழ்',
    loading: 'ஏற்றுகிறது...',
    no_data: 'தரவு இல்லை',
    error: 'பிழை',
    success: 'வெற்றி',
    save: 'சேமி',
    cancel: 'ரத்துசெய்',
    delete: 'அழி',
    edit: 'திருத்து',
    reply: 'பதில்',
    like: 'விரும்பு',
    unlike: 'விரும்பவில்லை',
    pin: 'பின்',
    unpin: 'பின் நீக்கு',
    delete_comment: 'கருத்தை அழி',
    edit_comment: 'கருத்தை திருத்து',
    comment_placeholder: 'கருத்தை எழுதுங்கள்...',
    reply_placeholder: 'பதிலை எழுதுங்கள்...',
    submit_comment: 'கருத்தை இடு',
    submit_reply: 'பதிலை இடு',
    no_comments: 'இதுவரை கருத்துகள் இல்லை. முதலில் இடுங்கள்!',
    overall_rating: 'ஒட்டுமொத்த மதிப்பீடு',
    out_of_5: 'க்கு 5',
    based_on: 'அடிப்படையில்',
    reviews: 'மதிப்புரைகள்',
    your_rating: 'உங்கள் மதிப்பீடு',
    rate_teacher: 'ஆசிரியரை மதிப்பிடுங்கள்',
    rate_course: 'பாடத்தை மதிப்பிடுங்கள்',
    attendance: 'வருகை',
    enrolled: 'பதிவு செய்தல்',
    not_enrolled: 'பதிவு செய்யவில்லை',
    course: 'பாடம்',
    teacher: 'ஆசிரியர்',
    student: 'மாணவர்',
    session: 'அமர்வு',
    comments: 'கருத்துகள்',
    ratings: 'மதிப்பீடுகள்',
    forum: 'மன்றம்',
    courses: 'பாடங்கள்',
    history: 'வரலாறு',
    overall: 'ஒட்டுமொத்த',
    tier: 'நிலை',
    settings: 'அமைப்புகள்',
    help: 'உதவி',
    dashboard: 'டாஷ்போர்டு',
    sign_out: 'வெளியேறு',
    welcome: 'வரவேற்பு',
    search: 'தேடு',
    filter: 'வடிகட்டி',
    all: 'அனைத்தும்',
    today: 'இன்று',
    this_week: 'இந்த வாரம்',
    this_month: 'இந்த மாதம்',
    students_base: 'மாணவர் தளம்',
    teachers: 'ஆசிரியர்கள்',
    lectures_export: 'விரிவுரைகள் மற்றும் ஏற்றுமதி',
    hardware_nodes: 'வன்பொருள் முனைகள்',
    sign_in: 'உள்நுழைக',
    sentinel_swarm: 'சென்டினெல் ஸ்வோர்ம்',
    brand_sub: 'AMS \u00B7 பெராதேனியா பல்கலைக்கழகம்',
    exit: 'வெளியேறு',
    node_ip: 'முனை IP',
    esp32_online: 'ESP32 இணைப்பில்',
    esp32_offline: 'ESP32 இணைப்பில் இல்லை',
    rpi_online: 'RPi இணைப்பில்',
    rpi_offline: 'RPi இணைப்பில் இல்லை',
    student_portal: 'மாணவர் நுழைவாயில்',
    teacher_portal: 'ஆசிரியர் நுழைவாயில்',
    total_students: 'மொத்த மாணவர்கள்',
    system_clock: 'கணினி கடிகாரம்',
    today_attendance: 'இன்றைய வருகை',
    live_logs: 'நேரலை வருகை பதிவுகள்',
    quick_actions: 'விரைவு செயல்கள்',
    recent_activity: 'சமீபத்திய செயல்பாடு',
    uptime: 'இயக்க நேரம்',
    last_seen: 'கடைசியாக பார்க்கப்பட்டது',
    minutes_ago: 'நிமிடம் முன்',
    seconds_ago: 'வினாடி முன்',
    password: 'கடவுச்சொல்',
    username_or_id: 'பயனர் பெயர் அல்லது மாணவர் ஐடி',
    enter_password: 'உங்கள் கடவுச்சொல்லை உள்ளிடவும்',
    student_login_hint: 'மாணவர்கள்: உங்கள் ஆரம்ப கடவுச்சொல் உங்கள் மாணவர் ஐடி ஆகும்.',
    contact_admin: 'உள்நுழைய முடியாவிட்டால் நிர்வாகியை தொடர்பு கொள்ளவும்.',
    fields_required: 'உங்கள் பயனர் பெயர் மற்றும் கடவுச்சொல் இரண்டையும் உள்ளிடவும்.',
    signing_in: 'உள்நுழைகிறது...',
    account_locked: 'கணக்கு தற்காலிகமாக பூட்டப்பட்டது',
    lock_message: 'அதிக தோல்வியுற்ற முயற்சிகள்.',
    please_wait: 'தயவுசெய்து காத்திருக்கவும்',
    seconds: 'வினாடி(கள்)',
    token_mismatch: 'டோக்கன் பொருந்தவில்லை.',
    invalid_credentials: 'தவறான சான்றுகள்.',
    attempts_remaining: 'முயற்சி(கள்) மீதம்.',
    session_expired: 'அமர்வு காலாவதியானது. மீண்டும் உள்நுழையவும்.',
    security_violation: 'பாதுகாப்பு மீறல் கண்டறியப்பட்டது.',
    absent_students: 'வருகை தராத மாணவர்கள்',
    attendance_percentage: 'வருகை சதவீதம்',
    no_students: 'மாணவர்கள் இல்லை',
    start_lecture: 'விரிவுரையை தொடங்கு',
    end_lecture: 'விரிவுரையை முடி',
    mark_present: 'வந்ததாக குறி',
    export_csv: 'CSV ஏற்றுமதி',
    edit_profile: 'சுயவிவரத்தை திருத்து',
    set_password: 'கடவுச்சொல்லை அமை',
    first_time_login: 'முதல் முறை உள்நுழைவு',
    you_must_change_password: 'நீங்கள் உங்கள் கடவுச்சொல்லை மாற்ற வேண்டும்.',
    new_password: 'புதிய கடவுச்சொல்',
    confirm_password: 'கடவுச்சொல்லை உறுதிப்படுத்து',
    cancel_and_logout: 'ரத்துசெய்து வெளியேறு',
    change_password: 'கடவுச்சொல்லை மாற்று',
    attendance_logs: 'வருகை பதிவுகள்',
    my_courses: 'எனது பாடங்கள்',
    enroll_students: 'மாணவர்களை சேர்',
    ratings_forum: 'மதிப்பீடுகள் மற்றும் மன்றம்',
    enroll_student: 'மாணவரை சேர்',
    attendance_charts: 'வருகை விளக்கப்படங்கள்',
    ratings_feedback: 'மதிப்பீடுகள் மற்றும் கருத்து',
    forum_discussion: 'மன்ற விவாதம்',
    daily_trend: 'தினசரி வருகைப் போக்கு',
    modality_split: 'முறைப் பிரிவு',
    course_distribution: 'பாட வாரியான வருகை',
    hourly_pattern: 'மணிநேர முறை',
    course_ratings: 'பாட மதிப்பீடுகள்',
    recent_comments: 'சமீபத்திய கருத்துகள்',
    course_discussion: 'பாட விவாதம்',
    mute: 'ஒலியடக்கு',
    all_devices: 'அனைத்து சாதனங்கள்',
    search_student: 'மாணவரைத் தேடு...',
    loading_live_logs: 'நேரலைப் பதிவுகள் ஏற்றுகிறது...',
    no_logs_found: 'வடிகட்டலுக்கு ஏற்ப பதிவுகள் எதுவும் இல்லை.',
    total_enrolled: 'மொத்த சேர்க்கை',
    registered_students: 'பதிவு செய்த மாணவர்கள்',
    checkins_today: 'இன்றைய வருகைகள்',
    system_time: 'கணினி நேரம்',
    include_columns: 'நெடுவரிசைகளைச் சேர்',
    date_from: 'தேதி முதல்',
    date_to: 'தேதி வரை',
    time_from: 'நேரம் முதல்',
    time_to: 'நேரம் வரை',
    device: 'சாதனம்',
    course: 'பாடம்',
    student_no: 'மாணவர் எண்',
    student_name: 'பெயர்',
    timestamp: 'நேர முத்திரை',
    modality: 'முறை',
    ip_address: 'IP முகவரி',
    offline_sync: 'ஆஃப்லைன் ஒத்திசைவு',
    log_id: 'பதிவு ID',
    no_ratings: 'இதுவரை மதிப்பீடுகள் இல்லை.',
    no_comments_yet: 'இதுவரை கருத்துகள் இல்லை.',
    select_course: 'ஒரு பாடத்தைத் தேர்ந்தெடுக்கவும்...',
    loading_ratings: 'மதிப்பீடுகள் ஏற்றுகிறது...',
    loading_comments: 'கருத்துகள் ஏற்றுகிறது...',
    no_discussion: 'இந்த பாடத்திற்கு இதுவரை விவாதம் இல்லை.',
    write_comment: 'கருத்து எழுதுங்கள்...',
    entries: 'உள்ளீடுகள்',
    days_7: '7 நாட்கள்',
    days_14: '14 நாட்கள்',
    days_30: '30 நாட்கள்',
    days_90: '90 நாட்கள்',
    post: 'இடுகை',
    all_courses: 'அனைத்து பாடங்கள்',
    web_dashboard: 'வலை டாஷ்போர்டு',
    leave_blank_all: 'அனைத்திற்கும் காலியாக விடவும்',
    export: 'ஏற்றுமதி',
    section_history: 'பகுதி வரலாறு',
    export_current_view: 'தற்போதைய காட்சியை ஏற்றுமதி செய்',
    advanced_export: 'மேம்பட்ட ஏற்றுமதி',
    clear_all: 'அனைத்தையும் அழி',
    download_selected: 'தேர்ந்தெடுத்ததை பதிவிறக்கம்',
    selected: 'தேர்ந்தெடுக்கப்பட்டது',
    no_sections: 'இதுவரை முடிக்கப்பட்ட பகுதிகள் இல்லை.',
    section: 'பகுதி',
    start: 'தொடக்கம்',
    end: 'முடிவு',
    duration: 'காலம்',
    download_csv: 'CSV பதிவிறக்கம்',
    clear_section_history: 'அனைத்து பகுதி வரலாற்றையும் அழிக்கவா?',
    total_attendance: 'மொத்த வருகை',
    today: 'இன்று',
    students: 'மாணவர்கள்',
    courses: 'பாடங்கள்',
    individual_analysis: 'தனி நபர் பகுப்பாய்வு',
    per_student: 'மாணவர் வாரியாக',
    per_course: 'பாடம் வாரியாக',
    per_teacher: 'ஆசிரியர் வாரியாக',
    course_trend: 'பாடப் போக்கு',
    student_trend: 'மாணவர் போக்கு',
  }
};

let _currentLang = 'en';

function setLanguage(lang) {
  if (!I18N[lang]) return;
  _currentLang = lang;
  localStorage.setItem('ams_lang', lang);
  document.documentElement.lang = lang;
  // Update all data-i18n elements
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.dataset.i18n;
    if (I18N[lang][key]) el.textContent = I18N[lang][key];
  });
  // Update placeholders
  document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
    const key = el.dataset.i18nPlaceholder;
    if (I18N[lang][key]) el.placeholder = I18N[lang][key];
  });
}

function t(key) {
  return I18N[_currentLang][key] || I18N.en[key] || key;
}

function getLang() { return _currentLang; }

/* ─── INIT LANGUAGE ──────────────────────────────────────────── */
(function initLang() {
  const saved = localStorage.getItem('ams_lang');
  if (saved && I18N[saved]) _currentLang = saved;
  document.documentElement.lang = _currentLang;
  // Apply translations once DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function applyLang() {
      setLanguage(_currentLang);
    });
  } else {
    setLanguage(_currentLang);
  }
})();

/* ═══════════════════════════════════════════════════════════════
   RENDER LANGUAGE SWITCHER
   ═══════════════════════════════════════════════════════════════ */
function renderLangSwitcher(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const langs = [
    { code: 'en', label: 'EN' },
    { code: 'si', label: 'සිං' },
    { code: 'ta', label: 'த' },
  ];
  let html = '<div class="lang-switcher">';
  langs.forEach(l => {
    html += `<button class="lang-btn${_currentLang === l.code ? ' active' : ''}" onclick="setLanguage('${l.code}'); renderLangSwitcher('${containerId}');" title="${I18N[l.code].lang_name}">${l.label}</button>`;
  });
  html += '</div>';
  container.innerHTML = html;
}

/* ═══════════════════════════════════════════════════════════════
   STAR RATINGS
   ═══════════════════════════════════════════════════════════════ */
function renderStars(containerId, targetType, targetId, readonly, currentRating) {
  const container = document.getElementById(containerId);
  if (!container) return;
  let html = '<div class="star-rating' + (readonly ? ' star-rating-readonly' : '') + '" data-target-type="' + targetType + '" data-target-id="' + targetId + '">';
  for (let i = 1; i <= 5; i++) {
    const active = currentRating >= i ? 'active' : '';
    html += '<span class="star ' + active + '" data-value="' + i + '" onclick="' + (readonly ? '' : 'submitRating(\'' + targetType + '\',\'' + targetId + '\',' + i + ')') + '" onmouseenter="hoverStar(this)" onmouseleave="unhoverStar(\'' + containerId + '\',' + (currentRating || 0) + ')"><i class="bi bi-star-fill"></i></span>';
  }
  html += '</div>';
  container.innerHTML = html;
}

function hoverStar(el) {
  const parent = el.closest('.star-rating');
  if (!parent || parent.classList.contains('star-rating-readonly')) return;
  const value = parseInt(el.dataset.value);
  parent.querySelectorAll('.star').forEach(s => {
    const sv = parseInt(s.dataset.value);
    s.classList.toggle('hover', sv <= value);
    s.classList.toggle('active', sv <= value);
  });
}

function unhoverStar(containerId, currentRating) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const parent = container.querySelector('.star-rating');
  if (!parent || parent.classList.contains('star-rating-readonly')) return;
  parent.querySelectorAll('.star').forEach(s => {
    const sv = parseInt(s.dataset.value);
    s.classList.toggle('active', sv <= (currentRating || 0));
    s.classList.remove('hover');
  });
}

function submitRating(targetType, targetId, stars) {
  const fd = new FormData();
  fd.append('action', 'submit_rating');
  fd.append('target_type', targetType);
  fd.append('target_id', targetId);
  fd.append('stars', stars);
  fetch('/csc2052/api/ratings.php', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': getCsrf() },
    body: fd
  })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        showToast('Rated ' + stars + ' stars!', 'success');
        loadRatingSummary(targetType, targetId);
      } else {
        showToast(data.message || 'Failed', 'error');
      }
    })
    .catch(() => showToast('Network error', 'error'));
}

function loadRatingSummary(targetType, targetId) {
  const summaryId = 'rating-summary-' + targetType + '-' + targetId;
  const el = document.getElementById(summaryId);
  if (!el) return;
  fetch('/csc2052/api/ratings.php?action=get_summary&target_type=' + targetType + '&target_id=' + encodeURIComponent(targetId))
    .then(r => r.json())
    .then(data => {
      if (data.status !== 'success') {
        el.innerHTML = '<span class="text-muted small">—</span>';
        return;
      }
      const avg = data.avg_stars || 0;
      const total = data.total_ratings || 0;
      const my = data.my_rating;
      let html = '<div class="d-flex align-items-center gap-2">';
      html += '<span class="star-display">' + '<i class="bi bi-star-fill"></i>'.repeat(Math.round(avg)) + '<i class="bi bi-star"></i>'.repeat(5 - Math.round(avg)) + '</span>';
      html += '<span class="fw-bold">' + avg + '</span>';
      html += '<span class="text-muted small">(' + total + ')</span>';
      if (my) {
        html += '<span class="badge bg-warning text-dark ms-2">Your: ' + my + ' <i class="bi bi-star-fill"></i></span>';
      }
      html += '</div>';
      // Render clickable stars for interactive rating
      const starsId = 'star-input-' + targetType + '-' + targetId;
      html += '<div id="' + starsId + '" class="mt-1"></div>';
      el.innerHTML = html;
      renderStars(starsId, targetType, targetId, false, my || 0);
    })
    .catch(() => { el.innerHTML = '<span class="text-muted small">—</span>'; });
}

/* ═══════════════════════════════════════════════════════════════
   FORUM COMMENTS
   ═══════════════════════════════════════════════════════════════ */
function loadComments(targetType, targetId, containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = '<div class="text-center text-muted py-3 small"><i class="bi bi-arrow-clockwise spin me-1"></i>' + t('loading') + '</div>';
  fetch('/csc2052/api/ratings.php?action=get_comments&target_type=' + targetType + '&target_id=' + encodeURIComponent(targetId) + '&limit=50')
    .then(r => r.json())
    .then(data => {
      if (!data.comments || data.comments.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-chat-dots fs-2 d-block mb-2"></i><p class="small mb-0">' + t('no_comments') + '</p></div>';
        return;
      }
      let html = '<div class="forum-thread">';
      data.comments.forEach(c => {
        html += renderCommentThread(c, targetType, targetId);
      });
      html += '</div>';
      container.innerHTML = html;
    })
    .catch(() => {
      container.innerHTML = '<div class="text-center text-danger py-3 small"><i class="bi bi-exclamation-triangle me-1"></i>' + t('error') + '</div>';
    });
}

function renderCommentThread(comment, targetType, targetId) {
  const isPinned = comment.is_pinned == 1;
  const isMine = comment.is_mine;
  const liked = comment.i_liked > 0;
  const likeIcon = liked ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up';
  const likeClass = liked ? 'liked' : '';
  let html = '<div class="forum-comment' + (isPinned ? ' pinned' : '') + '" data-comment-id="' + comment.id + '">';
  html += '<div class="comment-header">';
  html += '<div><span class="comment-author">' + esc(comment.author_name) + '</span>';
  if (comment.user_role === 'teacher') html += ' <span class="badge bg-primary" style="font-size:0.6rem;">Teacher</span>';
  if (comment.user_role === 'admin') html += ' <span class="badge bg-danger" style="font-size:0.6rem;">Admin</span>';
  if (isPinned) html += ' <span class="badge bg-warning text-dark" style="font-size:0.6rem;"><i class="bi bi-pin-angle-fill me-1"></i>Pinned</span>';
  html += '</div>';
  html += '<span class="comment-date">' + (comment.created_at ? new Date(comment.created_at.replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '') + '</span>';
  html += '</div>';
  html += '<div class="comment-body">' + esc(comment.body) + '</div>';
  html += '<div class="comment-actions">';
  html += '<button onclick="toggleLike(' + comment.id + ', this)" class="' + likeClass + '"><i class="bi ' + likeIcon + ' me-1"></i><span class="like-count">' + (comment.like_count || 0) + '</span></button>';
  html += '<button onclick="showReplyForm(' + comment.id + ', \'' + targetType + '\', \'' + targetId + '\')"><i class="bi bi-reply me-1"></i>' + t('reply') + '</button>';
  if (isMine) {
    html += '<button onclick="editComment(' + comment.id + ')"><i class="bi bi-pencil me-1"></i>' + t('edit') + '</button>';
    html += '<button onclick="deleteComment(' + comment.id + ')" class="text-danger"><i class="bi bi-trash me-1"></i>' + t('delete') + '</button>';
  }
  html += '</div>';
  // Reply form placeholder
  html += '<div id="reply-form-' + comment.id + '" class="mt-2" style="display:none;"></div>';
  // Replies
  if (comment.replies && comment.replies.length > 0) {
    html += '<div class="forum-replies">';
    comment.replies.forEach(r => {
      const rLiked = r.i_liked > 0;
      const rLikeIcon = rLiked ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up';
      const rLikeClass = rLiked ? 'liked' : '';
      const rIsMine = r.is_mine;
      html += '<div class="forum-comment" data-comment-id="' + r.id + '">';
      html += '<div class="comment-header"><span class="comment-author">' + esc(r.author_name) + '</span><span class="comment-date">' + (r.created_at ? new Date(r.created_at.replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '') + '</span></div>';
      html += '<div class="comment-body">' + esc(r.body) + '</div>';
      html += '<div class="comment-actions">';
      html += '<button onclick="toggleLike(' + r.id + ', this)" class="' + rLikeClass + '"><i class="bi ' + rLikeIcon + ' me-1"></i><span class="like-count">' + (r.like_count || 0) + '</span></button>';
      if (rIsMine) {
        html += '<button onclick="editComment(' + r.id + ')"><i class="bi bi-pencil me-1"></i>' + t('edit') + '</button>';
        html += '<button onclick="deleteComment(' + r.id + ')" class="text-danger"><i class="bi bi-trash me-1"></i>' + t('delete') + '</button>';
      }
      html += '</div></div>';
    });
    html += '</div>';
  }
  html += '</div>';
  return html;
}

function submitComment(targetType, targetId, containerId) {
  const textarea = document.getElementById('comment-text-' + targetType + '-' + targetId);
  const body = textarea ? textarea.value.trim() : '';
  if (!body || body.length < 2) { showToast('Comment too short', 'warning'); return; }
  const fd = new FormData();
  fd.append('action', 'submit_comment');
  fd.append('target_type', targetType);
  fd.append('target_id', targetId);
  fd.append('body', body);
  fetch('/csc2052/api/ratings.php', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': getCsrf() },
    body: fd
  })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        if (textarea) textarea.value = '';
        showToast('Comment posted!', 'success');
        loadComments(targetType, targetId, containerId);
      } else {
        showToast(data.message || 'Failed', 'error');
      }
    })
    .catch(() => showToast('Network error', 'error'));
}

function showReplyForm(parentId, targetType, targetId) {
  const el = document.getElementById('reply-form-' + parentId);
  if (!el) return;
  if (el.style.display !== 'none') { el.style.display = 'none'; return; }
  el.style.display = 'block';
  el.innerHTML = '<div class="input-group input-group-sm"><input type="text" class="form-control" id="reply-text-' + parentId + '" placeholder="' + t('reply_placeholder') + '"><button class="btn btn-primary btn-sm" onclick="submitReply(' + parentId + ',\'' + targetType + '\',\'' + targetId + '\')"><i class="bi bi-send me-1"></i></button></div>';
  document.getElementById('reply-text-' + parentId)?.focus();
}

function submitReply(parentId, targetType, targetId) {
  const input = document.getElementById('reply-text-' + parentId);
  const body = input ? input.value.trim() : '';
  if (!body || body.length < 2) { showToast('Reply too short', 'warning'); return; }
  const fd = new FormData();
  fd.append('action', 'submit_comment');
  fd.append('target_type', targetType);
  fd.append('target_id', targetId);
  fd.append('body', body);
  fd.append('parent_id', parentId);
  fetch('/csc2052/api/ratings.php', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': getCsrf() },
    body: fd
  })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        if (input) input.value = '';
        showToast('Reply posted!', 'success');
        loadComments(targetType, targetId, 'comments-' + targetType + '-' + targetId);
      } else {
        showToast(data.message || 'Failed', 'error');
      }
    })
    .catch(() => showToast('Network error', 'error'));
}

function toggleLike(commentId, btn) {
  const fd = new FormData();
  fd.append('action', 'toggle_like');
  fd.append('comment_id', commentId);
  fetch('/csc2052/api/ratings.php', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': getCsrf() },
    body: fd
  })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        btn.classList.toggle('liked', data.liked);
        btn.querySelector('.like-count').textContent = data.like_count;
        const icon = btn.querySelector('i');
        icon.className = data.liked ? 'bi bi-hand-thumbs-up-fill me-1' : 'bi bi-hand-thumbs-up me-1';
      }
    })
    .catch(() => {});
}

function deleteComment(commentId) {
  if (!confirm(t('delete_comment') + '?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_comment');
  fd.append('comment_id', commentId);
  fetch('/csc2052/api/ratings.php', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': getCsrf() },
    body: fd
  })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        showToast('Comment deleted', 'success');
        document.querySelector('[data-comment-id="' + commentId + '"]')?.remove();
      } else {
        showToast('Failed to delete', 'error');
      }
    });
}

function editComment(commentId) {
  const el = document.querySelector('[data-comment-id="' + commentId + '"] .comment-body');
  if (!el) return;
  const currentText = el.textContent;
  el.innerHTML = '<div class="input-group input-group-sm"><input type="text" class="form-control" value="' + esc(currentText) + '" id="edit-text-' + commentId + '"><button class="btn btn-success btn-sm" onclick="saveEdit(' + commentId + ')"><i class="bi bi-check"></i></button><button class="btn btn-secondary btn-sm" onclick="loadCommentsForCurrent()"><i class="bi bi-x"></i></button></div>';
  document.getElementById('edit-text-' + commentId)?.focus();
}

function saveEdit(commentId) {
  const input = document.getElementById('edit-text-' + commentId);
  const body = input ? input.value.trim() : '';
  if (!body || body.length < 2) { showToast('Comment too short', 'warning'); return; }
  const fd = new FormData();
  fd.append('action', 'edit_comment');
  fd.append('comment_id', commentId);
  fd.append('body', body);
  fetch('/csc2052/api/ratings.php', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': getCsrf() },
    body: fd
  })
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        showToast('Comment updated', 'success');
        loadCommentsForCurrent();
      } else {
        showToast(data.message || 'Failed', 'error');
      }
    });
}

// Placeholder — override in dashboard
function loadCommentsForCurrent() {}

/* ═══════════════════════════════════════════════════════════════
   ATTENDANCE TOOLTIP — fixed positioning, NEVER clipped by overflow
   ═══════════════════════════════════════════════════════════════ */
let _tooltipEl = null;
let _tooltipTimer = null;

function createTooltip() {
  if (!_tooltipEl) {
    _tooltipEl = document.createElement('div');
    _tooltipEl.className = 'att-tooltip-box';
    _tooltipEl.style.display = 'none';
    document.body.appendChild(_tooltipEl);
  }
  return _tooltipEl;
}

function positionTooltip(tip, mouseX, mouseY) {
  const vw = window.innerWidth;
  const vh = window.innerHeight;
  const tw = tip.offsetWidth || 220;
  const th = tip.offsetHeight || 120;
  const gap = 12;

  // Default: below cursor, centered
  let left = mouseX - tw / 2;
  let top = mouseY + gap;

  // Reset arrow classes
  tip.classList.remove('arrow-below', 'arrow-above', 'arrow-left', 'arrow-right');

  // Above if near bottom edge
  if (top + th > vh - 10) {
    top = mouseY - th - gap;
    tip.classList.add('arrow-above');
  } else {
    tip.classList.add('arrow-below');
  }

  // Left/Right adjust if off-screen horizontally
  if (left + tw > vw - 10) left = vw - tw - 10;
  if (left < 10) left = 10;

  tip.style.left = left + 'px';
  tip.style.top = top + 'px';
}

function showTooltip(e, studentNo) {
  const tip = createTooltip();
  tip.style.display = 'block';
  tip.style.opacity = '0';
  tip.innerHTML = '<div class="text-center py-2"><small><i class="bi bi-arrow-clockwise spin"></i> Loading...</small></div>';
  positionTooltip(tip, e.clientX, e.clientY);

  // Fade in
  requestAnimationFrame(() => { tip.style.opacity = '1'; });

  // Fetch data only once
  if (!tip.dataset.loaded || tip.dataset.student !== studentNo) {
    tip.dataset.student = studentNo;
    tip.dataset.loaded = '0';
    const cacheKey = 'att_tooltip_' + studentNo;
    const cached = sessionStorage.getItem(cacheKey);
    if (cached) {
      try {
        const data = JSON.parse(cached);
        renderTooltipContent(tip, studentNo, data);
        tip.dataset.loaded = '1';
        positionTooltip(tip, e.clientX, e.clientY);
        return;
      } catch (e) {}
    }
    fetch('/csc2052/api/student.php?action=get_my_courses&student_no=' + encodeURIComponent(studentNo))
      .then(r => r.json())
      .then(data => {
        renderTooltipContent(tip, studentNo, data);
        tip.dataset.loaded = '1';
        positionTooltip(tip, e.clientX, e.clientY);
        try { sessionStorage.setItem(cacheKey, JSON.stringify(data)); } catch (e) {}
      })
      .catch(() => {
        tip.innerHTML = '<div class="text-center py-2 text-muted"><small>Failed to load</small></div>';
        tip.dataset.loaded = '1';
      });
  }
}

function renderTooltipContent(tip, studentNo, data) {
  const courses = data.courses || [];
  if (courses.length === 0) {
    tip.innerHTML = '<div class="tooltip-title">' + esc(studentNo) + '</div><div class="text-muted" style="font-size:0.75rem;">Not enrolled in any courses</div>';
    return;
  }
  let html = '<div class="tooltip-title">' + esc(studentNo) + '</div>';
  courses.forEach(c => {
    const pct = c.percentage || 0;
    const color = pct >= 80 ? '#22c55e' : pct >= 50 ? '#eab308' : '#ef4444';
    html += '<div class="course-row"><span class="course-code">' + esc(c.course_code) + '</span><span class="course-pct" style="color:' + color + '">' + pct + '%</span></div>';
  });
  const overall = data.overall || 0;
  const overallColor = overall >= 80 ? '#22c55e' : overall >= 50 ? '#eab308' : '#ef4444';
  html += '<div class="tooltip-divider"></div><div class="course-row"><span style="font-weight:600;">Overall</span><span class="course-pct" style="color:' + overallColor + '">' + overall + '%</span></div>';
  if (data.tier) {
    html += '<div class="text-center"><span class="tooltip-tier-badge">' + data.tier + '</span></div>';
  }
  tip.innerHTML = html;
}

function hideTooltip() {
  if (_tooltipEl) {
    _tooltipEl.style.opacity = '0';
    setTimeout(() => { if (_tooltipEl) _tooltipEl.style.display = 'none'; }, 150);
  }
}

function attachAttendanceTooltips() {
  document.querySelectorAll('[data-att-tooltip]').forEach(el => {
    if (el.dataset.attLoaded) return;
    el.dataset.attLoaded = '1';
    el.classList.add('att-tooltip', 'att-tooltip-glow');
    const studentNo = el.dataset.attTooltip;
    el.addEventListener('mouseenter', function(e) { showTooltip(e, studentNo); });
    el.addEventListener('mousemove', function(e) {
      if (_tooltipEl && _tooltipEl.style.display !== 'none') {
        positionTooltip(_tooltipEl, e.clientX, e.clientY);
      }
    });
    el.addEventListener('mouseleave', hideTooltip);
  });
}

/* ─── INIT TOOLTIPS ON LOAD + DYNAMIC CONTENT ─────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(attachAttendanceTooltips, 500);
  const observer = new MutationObserver(() => setTimeout(attachAttendanceTooltips, 300));
  observer.observe(document.body, { childList: true, subtree: true });
});

/* ═══════════════════════════════════════════════════════════════
   BUTTON RIPPLE EFFECT — click ripple for .btn-hover-ripple / .btn-fx
   ═══════════════════════════════════════════════════════════════ */
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.btn-hover-ripple, .btn-fx');
  if (!btn) return;
  const rect = btn.getBoundingClientRect();
  const ripple = document.createElement('span');
  ripple.className = 'ripple-span';
  const size = Math.max(rect.width, rect.height) * 0.6;
  const x = e.clientX - rect.left - size / 2;
  const y = e.clientY - rect.top - size / 2;
  ripple.style.cssText = 'width:' + size + 'px;height:' + size + 'px;left:' + x + 'px;top:' + y + 'px;';
  if (getComputedStyle(btn).position === 'static') btn.style.position = 'relative';
  btn.style.overflow = 'hidden';
  btn.appendChild(ripple);
  ripple.addEventListener('animationend', function() { ripple.remove(); });
});

/* ─── TABLE ROW MOUSEOVER GLOW ──────────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('table.table-hover tbody tr').forEach(function(row) {
    row.style.transition = 'background-color 0.2s ease, box-shadow 0.2s ease';
    row.addEventListener('mouseenter', function() {
      this.style.boxShadow = 'inset 2px 0 0 var(--clr-primary)';
    });
    row.addEventListener('mouseleave', function() {
      this.style.boxShadow = 'none';
    });
  });
});

/* ─── KEYBOARD SHORTCUT: ESC to close tooltip ────────────────── */
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') hideTooltip();
});

/* ─── SMOOTH SCROLL FOR ANCHOR LINKS ─────────────────────────── */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
});
