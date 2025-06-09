<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Add candidate
    if ($action === 'add') {
        $firstName = trim($_POST['firstName'] ?? '');
        $middleName = trim($_POST['middleName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $partylist = $_POST['partylist'] ?? '';
        $position = $_POST['position'] ?? '';
        $credentials = trim($_POST['credentials'] ?? '');
        $year = trim($_POST['year'] ?? '');

        // Validate required
        if (!$firstName || !$lastName || !$position) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill required fields']);
            exit;
        }

        // Handle image upload if exists
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['image']['tmp_name'];
            $fileName = basename($_FILES['image']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($fileExt, $allowed)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid image format']);
                exit;
            }

            $newFileName = uniqid('cand_') . '.' . $fileExt;
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $dest = $uploadDir . $newFileName;

            if (!move_uploaded_file($fileTmp, $dest)) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to upload image']);
                exit;
            }

            $imagePath = 'uploads/' . $newFileName;
        }

        // Insert into DB
        $stmt = $pdo->prepare("INSERT INTO candidates 
            (first_name, middle_name, last_name, partylist, position, credentials, year, image_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $firstName, $middleName, $lastName, $partylist, $position, $credentials, $year, $imagePath
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Candidate added']);
        exit;
    }

    // Delete candidate
    if ($action === 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        // Delete image file
        $stmt = $pdo->prepare("SELECT image_path FROM candidates WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        if ($img && file_exists(__DIR__ . '/' . $img)) {
            unlink(__DIR__ . '/' . $img);
        }

        $stmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success', 'message' => 'Candidate deleted']);
        exit;
    }

    // Edit candidate - for simplicity, only basic data update (no image change)
    if ($action === 'edit' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $firstName = trim($_POST['firstName'] ?? '');
        $middleName = trim($_POST['middleName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $partylist = $_POST['partylist'] ?? '';
        $position = $_POST['position'] ?? '';
        $credentials = trim($_POST['credentials'] ?? '');
        $year = trim($_POST['year'] ?? '');

        if (!$firstName || !$lastName || !$position) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill required fields']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE candidates SET
            first_name = ?, middle_name = ?, last_name = ?, partylist = ?, position = ?, credentials = ?, year = ?
            WHERE id = ?");
        $stmt->execute([$firstName, $middleName, $lastName, $partylist, $position, $credentials, $year, $id]);

        echo json_encode(['status' => 'success', 'message' => 'Candidate updated']);
        exit;
    }

    // Fallback
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

// Fetch candidates grouped by position for initial page load
$stmt = $pdo->query("SELECT * FROM candidates ORDER BY full_name");
$candidatesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
$candidates = [];
foreach ($candidatesRaw as $cand) {
    $pos = strtoupper($cand['position']);
    if (!isset($candidates[$pos])) $candidates[$pos] = [];
    $candidates[$pos][] = $cand;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Manage Candidates</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 min-h-screen">
<div class="flex">

<!-- Sidebar -->
<div class="w-64 bg-white shadow-lg min-h-screen">
    <div class="p-6 border-b">
        <div class="w-20 h-20 bg-gray-200 border-2 border-gray-400 flex items-center justify-center">
            <div class="text-2xl text-gray-600"><i class="fas fa-cog"></i></div>
        </div>
    </div>
    <div class="p-4">
        <div class="flex items-center space-x-3 mb-6">
            <div class="w-8 h-8 bg-gray-800 flex items-center justify-center rounded-full">
                <i class="fas fa-user-shield text-white text-sm"></i>
            </div>
            <span class="font-semibold text-gray-800">ADMIN</span>
        </div>
        <nav class="space-y-2">
            <a href="#" class="flex items-center space-x-3 p-3 text-gray-600 hover:bg-gray-100 rounded">
                <i class="fas fa-th-large"></i>
                <span>Overview</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 text-gray-600 hover:bg-gray-100 rounded">
                <i class="fas fa-flag"></i>
                <span>Partylist</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 text-gray-600 hover:bg-gray-100 rounded">
                <i class="fas fa-user-tie"></i>
                <span>Position</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 bg-gray-200 text-gray-800 rounded font-medium">
                <i class="fas fa-user"></i>
                <span>Candidate</span>
            </a>
        </nav>
    </div>
</div>

<!-- Main Content -->
<div class="flex-1 p-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center space-x-4">
            <button class="p-2 hover:bg-gray-200 rounded-full" onclick="history.back()">
                <i class="fas fa-arrow-left text-gray-600"></i>
            </button>
            <h1 class="text-2xl font-bold text-gray-800">Manage Candidates</h1>
        </div>
        <div class="flex items-center space-x-4">
            <button id="toggleFormBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-plus"></i><span>Add Candidate</span>
            </button>
            <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-gray-600"></i>
            </div>
        </div>
    </div>

    <!-- Add/Edit Candidate Form -->
    <div id="candidateFormContainer" class="bg-white rounded-lg shadow-md p-6 mb-8 hidden">
        <h2 id="formTitle" class="text-xl font-semibold mb-4">Add Candidate</h2>
        <form id="candidateForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add" />
            <input type="hidden" name="id" id="candidateId" />
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                    <input type="text" name="firstName" id="firstName" required
                        class="w-full rounded border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                    <input type="text" name="middleName" id="middleName"
                        class="w-full rounded border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                    <input type="text" name="lastName" id="lastName" required
                        class="w-full rounded border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Partylist</label>
                    <input type="text" name="partylist" id="partylist" placeholder="(Optional)"
                        class="w-full rounded border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Position *</label>
                    <select name="position" id="position" required
                        class="w-full rounded border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Position</option>
                        <option value="President">President</option>
                        <option value="Vice-President">Vice-President</option>
                        <option value="Secretary">Secretary</option>
                        <option value="Treasurer">Treasurer</option>
                        <option value="Auditor">Auditor</option>
                        <option value="Business Manager">Business Manager</option>
                        <!-- Add more positions as needed -->
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Credentials</label>
                    <input type="text" name="credentials" id="credentials" placeholder="e.g. BS Computer Science"
                        class="w-full rounded border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                    <input type="text" name="year" id="year" placeholder="e.g. 3rd Year"
                        class="w-full rounded border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Candidate Image</label>
                <input type="file" name="image" id="image" accept="image/*"
                    class="w-full rounded border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                <img id="previewImage" src="" alt="Preview" class="mt-3 max-h-48 hidden rounded border" />
            </div>

            <div class="flex justify-end space-x-4">
                <button type="button" id="cancelBtn"
                    class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" id="submitBtn"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            </div>
        </form>
    </div>

    <!-- Candidates List -->
    <div id="candidatesContainer" class="space-y-8">
        <?php foreach ($candidates as $pos => $posCandidates): ?>
            <section>
                <h2 class="text-lg font-semibold text-gray-700 mb-4"><?=htmlspecialchars($pos)?></h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($posCandidates as $cand): ?>
                        <div
                            class="bg-white rounded-lg shadow p-4 flex flex-col items-center text-center relative group hover:shadow-lg transition-shadow">
                            <img src="<?=htmlspecialchars($cand['image_path'] ?: 'https://cdn-icons-png.flaticon.com/512/149/149071.png')?>"
                                alt="Candidate Image" class="w-24 h-24 rounded-full object-cover mb-4 border border-gray-300" />
                            <h3 class="text-md font-semibold mb-1"><?=htmlspecialchars($cand['first_name'] . ' ' . $cand['last_name'])?></h3>
                            <?php if ($cand['partylist']): ?>
                                <p class="text-sm text-gray-500 mb-1"><?=htmlspecialchars($cand['partylist'])?></p>
                            <?php endif; ?>
                            <?php if ($cand['credentials']): ?>
                                <p class="text-xs text-gray-400 mb-1"><?=htmlspecialchars($cand['credentials'])?></p>
                            <?php endif; ?>
                            <?php if ($cand['year']): ?>
                                <p class="text-xs text-gray-400 mb-1"><?=htmlspecialchars($cand['year'])?></p>
                            <?php endif; ?>

                            <!-- Edit/Delete Buttons, visible on hover -->
                            <div
                                class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity flex space-x-2">
                                <button data-id="<?=$cand['id']?>" class="editBtn p-1 bg-yellow-300 rounded hover:bg-yellow-400"
                                    title="Edit Candidate">
                                    <i class="fas fa-pencil-alt text-yellow-800"></i>
                                </button>
                                <button data-id="<?=$cand['id']?>" class="deleteBtn p-1 bg-red-500 rounded hover:bg-red-600"
                                    title="Delete Candidate">
                                    <i class="fas fa-trash text-white"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

</div>

<script>
const toggleFormBtn = document.getElementById('toggleFormBtn');
const candidateFormContainer = document.getElementById('candidateFormContainer');
const candidateForm = document.getElementById('candidateForm');
const cancelBtn = document.getElementById('cancelBtn');
const previewImage = document.getElementById('previewImage');
const imageInput = document.getElementById('image');
const formTitle = document.getElementById('formTitle');
const candidateIdInput = document.getElementById('candidateId');
const submitBtn = document.getElementById('submitBtn');

toggleFormBtn.addEventListener('click', () => {
    clearForm();
    candidateFormContainer.classList.toggle('hidden');
    formTitle.textContent = 'Add Candidate';
    candidateForm.action.value = 'add';
});

cancelBtn.addEventListener('click', () => {
    clearForm();
    candidateFormContainer.classList.add('hidden');
});

imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];
    if (!file) {
        previewImage.src = '';
        previewImage.classList.add('hidden');
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        previewImage.src = e.target.result;
        previewImage.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
});

candidateForm.addEventListener('submit', async e => {
    e.preventDefault();

    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';

    const formData = new FormData(candidateForm);

    try {
        const resp = await fetch('', {
            method: 'POST',
            body: formData
        });
        const data = await resp.json();

        if (data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (err) {
        alert('Unexpected error occurred.');
        console.error(err);
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save';
    }
});

function clearForm() {
    candidateForm.reset();
    previewImage.src = '';
    previewImage.classList.add('hidden');
    candidateIdInput.value = '';
}

// Edit buttons
document.querySelectorAll('.editBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        fetchCandidate(id);
    });
});

async function fetchCandidate(id) {
    // Fetch candidate data from server (simple way: pass id to PHP and return JSON)
    // Here, let's just reload page with id param or implement another endpoint.
    // For demo, do a fetch call to the same PHP with GET param:

    const resp = await fetch(`?candidate_id=${id}&json=1`);
    if (!resp.ok) {
        alert('Failed to fetch candidate info');
        return;
    }
    const data = await resp.json();
    if (!data || data.status !== 'success') {
        alert('Candidate not found');
        return;
    }
    fillForm(data.candidate);
}

function fillForm(cand) {
    candidateFormContainer.classList.remove('hidden');
    formTitle.textContent = 'Edit Candidate';
    candidateForm.action.value = 'edit';
    candidateIdInput.value = cand.id;
    candidateForm.firstName.value = cand.first_name;
    candidateForm.middleName.value = cand.middle_name;
    candidateForm.lastName.value = cand.last_name;
    candidateForm.partylist.value = cand.partylist;
    candidateForm.position.value = cand.position;
    candidateForm.credentials.value = cand.credentials;
    candidateForm.year.value = cand.year;

    if (cand.image_path) {
        previewImage.src = cand.image_path;
        previewImage.classList.remove('hidden');
    } else {
        previewImage.src = '';
        previewImage.classList.add('hidden');
    }
}

// Delete buttons
document.querySelectorAll('.deleteBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        if (!confirm('Delete this candidate?')) return;
        const id = btn.getAttribute('data-id');
        deleteCandidate(id);
    });
});

async function deleteCandidate(id) {
    try {
        const resp = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action: 'delete', id})
        });
        const data = await resp.json();
        if (data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (err) {
        alert('Unexpected error occurred.');
        console.error(err);
    }
}
</script>

<?php
// Handle candidate fetch for editing (GET request)
if (isset($_GET['candidate_id'], $_GET['json'])) {
    $id = intval($_GET['candidate_id']);
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
    $stmt->execute([$id]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($candidate) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'candidate' => $candidate]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Candidate not found']);
    }
    exit;
}
?>

</body>
</html>
