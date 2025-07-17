<?php
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
$host = 'localhost';
$db   = 'evoting_system';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Session timeout 1 hour ---
$timeout_duration = 3600;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=Session expired. Please login again.');
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// --- Check if logged in and admin ---
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit();
}

// --- Handle Form Submission ---
$errors = [];
$success = '';

$full_name = $position = $party_list = $credentials = $manifesto = $platform = '';
$election_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $election_id = $_POST['election_id'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $party_list = trim($_POST['party_list'] ?? '');
    $credentials = trim($_POST['credentials'] ?? '');
    $manifesto = trim($_POST['manifesto'] ?? '');
    $platform = trim($_POST['platform'] ?? '');

    // Validate required fields
    if (empty($election_id)) {
        $errors[] = "Election selection is required.";
    }
    if (empty($full_name)) {
        $errors[] = "Full Name is required.";
    }
    if (empty($position)) {
        $errors[] = "Position is required.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO candidates (election_id, full_name, position, party_list, credentials, manifesto, platform) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$election_id, $full_name, $position, $party_list, $credentials, $manifesto, $platform]);
            $success = "Candidate added successfully.";

            // Clear form values after success
            $full_name = $position = $party_list = $credentials = $manifesto = $platform = '';
            $election_id = '';
        } catch (PDOException $e) {
            $errors[] = "Error adding candidate: " . $e->getMessage();
        }
    }
}

// Get elections for dropdown
$elections = $pdo->query("SELECT election_id, title FROM elections ORDER BY start_datetime DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Add Candidate - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    
    .glass-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .floating-label {
      transition: all 0.2s ease;
    }
    
    .input-group:focus-within .floating-label {
      transform: translateY(-1.5rem) scale(0.85);
      color: var(--cvsu-green);
    }
    
    .input-group input:not(:placeholder-shown) + .floating-label,
    .input-group select:not([value=""]) + .floating-label,
    .input-group textarea:not(:placeholder-shown) + .floating-label {
      transform: translateY(-1.5rem) scale(0.85);
      color: var(--cvsu-green);
    }
    
    .gradient-bg {
      background: linear-gradient(135deg, var(--cvsu-green-dark) 0%, var(--cvsu-green) 100%);
    }
    
    .form-input:focus {
      transform: translateY(-1px);
      box-shadow: 0 4px 20px rgba(30, 111, 70, 0.1);
    }
    
    .step-indicator {
      position: relative;
    }
    
    .step-indicator::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 100%;
      width: 50px;
      height: 2px;
      background: #e5e7eb;
      transform: translateY(-50%);
    }
    
    .step-indicator.active::after {
      background: var(--cvsu-green);
    }
    
    .step-indicator:last-child::after {
      display: none;
    }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">

  <!-- Background Pattern -->
  <div class="fixed inset-0 opacity-5 pointer-events-none">
    <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><circle cx=\"50\" cy=\"50\" r=\"2\" fill=\"%23154734\"/></svg>'); background-size: 20px 20px;"></div>
  </div>

  <div class="relative z-10 min-h-screen">
    <!-- Header -->
    <header class="gradient-bg text-white shadow-2xl">
      <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
              <i class="fas fa-user-plus text-xl"></i>
            </div>
            <div>
              <h1 class="text-3xl font-bold">Add New Candidate</h1>
              <p class="text-green-100 mt-1">Complete the form to register a new candidate</p>
            </div>
          </div>
          <a href="manage_candidates.php" class="flex items-center space-x-2 px-6 py-3 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition-all duration-200 backdrop-blur-sm">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Candidates</span>
          </a>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-6 py-12">
      
      <!-- Progress Steps -->
      <div class="mb-12">
        <div class="flex items-center justify-center space-x-4">
          <div class="step-indicator active flex items-center">
            <div class="w-10 h-10 bg-green-600 text-white rounded-full flex items-center justify-center font-bold">
              1
            </div>
            <span class="ml-3 text-green-600 font-medium">Basic Info</span>
          </div>
          <div class="step-indicator flex items-center">
            <div class="w-10 h-10 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center font-bold">
              2
            </div>
            <span class="ml-3 text-gray-500 font-medium">Details</span>
          </div>
          <div class="step-indicator flex items-center">
            <div class="w-10 h-10 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center font-bold">
              3
            </div>
            <span class="ml-3 text-gray-500 font-medium">Platform</span>
          </div>
        </div>
      </div>

      <!-- Alert Messages -->
      <div id="alertContainer" class="mb-8"></div>

      <!-- Form Card -->
      <div class="glass-card rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-8">
          <form id="candidateForm" method="POST" class="space-y-8" novalidate>

            <!-- Section 1: Basic Information -->
            <div class="form-section active" id="section1">
              <div class="flex items-center mb-6">
                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                  <i class="fas fa-user text-green-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Basic Information</h2>
              </div>

              <div class="grid md:grid-cols-2 gap-6">
                <!-- Election Selection -->
                <div class="md:col-span-2">
                  <div class="input-group relative">
                    <select name="election_id" id="election_id" required class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 bg-white">
                      <option value="">Select Election</option>
                      <?php foreach ($elections as $election): ?>
                        <option value="<?= htmlspecialchars($election['election_id']) ?>" <?= ($election_id == $election['election_id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($election['title']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                      Election <span class="text-red-500">*</span>
                    </label>
                  </div>
                </div>

                <!-- Full Name -->
                <div class="md:col-span-2">
                  <div class="input-group relative">
                    <input type="text" name="full_name" id="full_name" required placeholder=" " class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 peer">
                    <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2 peer-focus:transform peer-focus:translate-y-[-1.5rem] peer-focus:scale-85 peer-focus:text-green-600 peer-[:not(:placeholder-shown)]:transform peer-[:not(:placeholder-shown)]:translate-y-[-1.5rem] peer-[:not(:placeholder-shown)]:scale-85 peer-[:not(:placeholder-shown)]:text-green-600">
                      Full Name <span class="text-red-500">*</span>
                    </label>
                  </div>
                </div>

                <!-- Position -->
                <div>
                  <div class="input-group relative">
                    <select name="position" id="position" required class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 bg-white">
                      <option value="">Select Position</option>
                      <option value="President">President</option>
                      <option value="Vice President">Vice President</option>
                      <option value="Secretary">Secretary</option>
                      <option value="Treasurer">Treasurer</option>
                      <option value="Auditor">Auditor</option>
                      <option value="Public Relations Officer">Public Relations Officer</option>
                      <option value="Senator">Senator</option>
                      <option value="Representative">Representative</option>
                    </select>
                    <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2">
                      Position <span class="text-red-500">*</span>
                    </label>
                  </div>
                </div>

                <!-- Party List -->
                <div>
                  <div class="input-group relative">
                    <input type="text" name="party_list" id="party_list" placeholder=" " class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 peer">
                    <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2 peer-focus:transform peer-focus:translate-y-[-1.5rem] peer-focus:scale-85 peer-focus:text-green-600 peer-[:not(:placeholder-shown)]:transform peer-[:not(:placeholder-shown)]:translate-y-[-1.5rem] peer-[:not(:placeholder-shown)]:scale-85 peer-[:not(:placeholder-shown)]:text-green-600">
                      Party List (Optional)
                    </label>
                  </div>
                </div>
              </div>

              <div class="flex justify-end mt-8">
                <button type="button" onclick="nextSection()" class="flex items-center space-x-2 px-8 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                  <span>Next</span>
                  <i class="fas fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Section 2: Credentials -->
            <div class="form-section hidden" id="section2">
              <div class="flex items-center mb-6">
                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                  <i class="fas fa-certificate text-blue-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Credentials & Experience</h2>
              </div>

              <div class="space-y-6">
                <div class="input-group relative">
                  <textarea name="credentials" id="credentials" rows="4" placeholder=" " class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 peer resize-none"></textarea>
                  <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2 peer-focus:transform peer-focus:translate-y-[-1.5rem] peer-focus:scale-85 peer-focus:text-green-600 peer-[:not(:placeholder-shown)]:transform peer-[:not(:placeholder-shown)]:translate-y-[-1.5rem] peer-[:not(:placeholder-shown)]:scale-85 peer-[:not(:placeholder-shown)]:text-green-600">
                    Credentials & Qualifications
                  </label>
                  <div class="text-xs text-gray-500 mt-2 flex items-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    Include educational background, relevant experience, and achievements
                  </div>
                </div>
              </div>

              <div class="flex justify-between mt-8">
                <button type="button" onclick="prevSection()" class="flex items-center space-x-2 px-8 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
                  <i class="fas fa-arrow-left"></i>
                  <span>Previous</span>
                </button>
                <button type="button" onclick="nextSection()" class="flex items-center space-x-2 px-8 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                  <span>Next</span>
                  <i class="fas fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Section 3: Platform -->
            <div class="form-section hidden" id="section3">
              <div class="flex items-center mb-6">
                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                  <i class="fas fa-bullhorn text-purple-600"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800">Platform & Vision</h2>
              </div>

              <div class="space-y-6">
                <div class="input-group relative">
                  <textarea name="manifesto" id="manifesto" rows="4" placeholder=" " class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 peer resize-none"></textarea>
                  <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2 peer-focus:transform peer-focus:translate-y-[-1.5rem] peer-focus:scale-85 peer-focus:text-green-600 peer-[:not(:placeholder-shown)]:transform peer-[:not(:placeholder-shown)]:translate-y-[-1.5rem] peer-[:not(:placeholder-shown)]:scale-85 peer-[:not(:placeholder-shown)]:text-green-600">
                    Manifesto
                  </label>
                  <div class="text-xs text-gray-500 mt-2 flex items-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    Describe your vision and core beliefs
                  </div>
                </div>

                <div class="input-group relative">
                  <textarea name="platform" id="platform" rows="4" placeholder=" " class="form-input w-full p-4 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0 transition-all duration-200 peer resize-none"></textarea>
                  <label class="floating-label absolute left-4 top-4 text-gray-500 pointer-events-none bg-white px-2 peer-focus:transform peer-focus:translate-y-[-1.5rem] peer-focus:scale-85 peer-focus:text-green-600 peer-[:not(:placeholder-shown)]:transform peer-[:not(:placeholder-shown)]:translate-y-[-1.5rem] peer-[:not(:placeholder-shown)]:scale-85 peer-[:not(:placeholder-shown)]:text-green-600">
                    Platform & Agenda
                  </label>
                  <div class="text-xs text-gray-500 mt-2 flex items-center">
                    <i class="fas fa-info-circle mr-1"></i>
                    Outline your specific plans and initiatives
                  </div>
                </div>
              </div>

              <div class="flex justify-between mt-8">
                <button type="button" onclick="prevSection()" class="flex items-center space-x-2 px-8 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
                  <i class="fas fa-arrow-left"></i>
                  <span>Previous</span>
                </button>
                <button type="button" onclick="showConfirmationModal()" class="flex items-center space-x-2 px-8 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                  <i class="fas fa-check"></i>
                  <span>Add Candidate</span>
                </button>
              </div>
            </div>

          </form>
        </div>
      </div>
    </main>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden backdrop-blur-sm">
    <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl transform transition-all duration-300">
      <div class="text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <i class="fas fa-user-check text-green-600 text-2xl"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">Confirm Registration</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to register this candidate? Please review all information before confirming.</p>
        
        <div class="flex space-x-3">
          <button onclick="hideConfirmationModal()" class="flex-1 px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition-all duration-200">
            Cancel
          </button>
          <button onclick="submitForm()" class="flex-1 px-6 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition-all duration-200 shadow-lg">
            Confirm
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    let currentSection = 1;
    const totalSections = 3;

    function showSection(sectionNum) {
      // Hide all sections
      for (let i = 1; i <= totalSections; i++) {
        document.getElementById(`section${i}`).classList.add('hidden');
      }
      
      // Show current section
      document.getElementById(`section${sectionNum}`).classList.remove('hidden');
      
      // Update progress indicators
      updateProgressIndicators(sectionNum);
    }

    function updateProgressIndicators(activeSection) {
      const indicators = document.querySelectorAll('.step-indicator');
      indicators.forEach((indicator, index) => {
        const stepNum = index + 1;
        const circle = indicator.querySelector('div');
        const text = indicator.querySelector('span');
        
        if (stepNum <= activeSection) {
          circle.classList.remove('bg-gray-300', 'text-gray-600');
          circle.classList.add('bg-green-600', 'text-white');
          text.classList.remove('text-gray-500');
          text.classList.add('text-green-600');
          indicator.classList.add('active');
        } else {
          circle.classList.remove('bg-green-600', 'text-white');
          circle.classList.add('bg-gray-300', 'text-gray-600');
          text.classList.remove('text-green-600');
          text.classList.add('text-gray-500');
          indicator.classList.remove('active');
        }
      });
    }

    function nextSection() {
      if (validateCurrentSection()) {
        if (currentSection < totalSections) {
          currentSection++;
          showSection(currentSection);
        }
      }
    }

    function prevSection() {
      if (currentSection > 1) {
        currentSection--;
        showSection(currentSection);
      }
    }

    function validateCurrentSection() {
      const currentSectionElement = document.getElementById(`section${currentSection}`);
      const requiredFields = currentSectionElement.querySelectorAll('[required]');
      let isValid = true;

      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          field.classList.add('border-red-500');
          isValid = false;
        } else {
          field.classList.remove('border-red-500');
        }
      });

      if (!isValid) {
        showAlert('Please fill all required fields before proceeding.', 'error');
      }

      return isValid;
    }

    function showConfirmationModal() {
      if (validateCurrentSection()) {
        document.getElementById('confirmationModal').classList.remove('hidden');
      }
    }

    function hideConfirmationModal() {
      document.getElementById('confirmationModal').classList.add('hidden');
    }

    function submitForm() {
      document.getElementById('candidateForm').submit();
    }

    function showAlert(message, type) {
      const alertContainer = document.getElementById('alertContainer');
      const alertClass = type === 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700';
      
      alertContainer.innerHTML = `
        <div class="${alertClass} px-4 py-3 rounded-xl border flex items-center space-x-3">
          <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i>
          <span>${message}</span>
        </div>
      `;
      
      setTimeout(() => {
        alertContainer.innerHTML = '';
      }, 5000);
    }

    // Initialize form
    document.addEventListener('DOMContentLoaded', function() {
      showSection(1);
      
      // Handle floating labels for selects
      document.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', function() {
          const label = this.parentElement.querySelector('.floating-label');
          if (this.value) {
            label.style.transform = 'translateY(-1.5rem) scale(0.85)';
            label.style.color = 'var(--cvsu-green)';
          } else {
            label.style.transform = '';
            label.style.color = '';
          }
        });
      });
    });
  </script>

</body>
</html>