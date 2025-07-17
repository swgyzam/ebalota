[Para maging organize nung file aayusin sa bakasyon]
/evoting-system/
│
├── /admin/                   # All admin-related pages and functionality
│   ├── admin_dashboard.php
│   ├── admin_login_pending.php
│   ├── admin_pass.php
│   ├── admin_verify_token.php
│   ├── manage_candidates.php
│   ├── manage_elections.php
│   ├── manage_users.php
│   ├── sidebar.php
│   ├── voters_sidebar.php
│   └── debug.log
│
├── /candidates/             # Candidate management
│   ├── add_candidate.php
│   ├── assign_candidates.php
│   ├── save_assignments.php
│   ├── tempo_add_candidate.php
│   ├── try.php
│   └── view_candidates.php
│
├── /elections/              # Election creation and management
│   ├── create_election.php
│   ├── delete_election.php
│   ├── update_election.php
│   ├── update_election.js
│   ├── get_election.php
│   ├── create_election.js
│
├── /auth/                   # Login, register, password reset, etc.
│   ├── login.php
│   ├── login.html
│   ├── register.php
│   ├── register.html
│   ├── register.js
│   ├── forgot_password.php
│   ├── forgot_password.html
│   ├── reset_password.php
│   ├── resend_verification.php
│   ├── verify_email.php
│   ├── logout.php
│   ├── process_voters.php
│   └── generate_test_token.php
│
├── /voters/                 # Voter pages and actions
│   ├── voters_dashboard.php
│   ├── submit_vote.php
│   ├── restrict_voters.csv
│   ├── upload_voters.php
│
├── /assets/                 # CSS, JS, and other frontend assets
│   ├── /css/
│   ├── /js/
│   └── (organized per your needs)
│
├── /uploads/                # Folder for uploaded files
│
├── /phpmailer/              # PHPMailer library
│   └── /src/
│
├── /database/               # Database scripts and config
│   ├── config.php
│   ├── evoting_system.sql
│   ├── database.txt
│
├── README.md
├── index.html
├── footer.php
└── update_debug.log

