<?php 
session_start();
date_default_timezone_set('Asia/Manila');

// --- DB Connection ---
 $host = 'localhost';
 $db = 'evoting_system';
 $user = 'root';
 $pass = '';
 $charset = 'utf8mb4';

 $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
 $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}

// --- Auth check ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Verify this is a COOP Admin
 $stmt = $pdo->prepare("SELECT role, assigned_scope FROM users WHERE user_id = ?");
 $stmt->execute([$_SESSION['user_id']]);
 $userInfo = $stmt->fetch();

 $role = $userInfo['role'] ?? '';
 $scope = strtoupper(trim($userInfo['assigned_scope'] ?? ''));

if ($scope !== 'COOP') {
    header('Location: admin_analytics.php');
    exit();
}

// Get election ID from URL
 $electionId = $_GET['id'] ?? 0;
if (!$electionId) {
    header('Location: admin_analytics_coop.php');
    exit();
}

// Fetch election details (only COOP elections)
 $stmt = $pdo->prepare("SELECT * FROM elections WHERE election_id = ? AND target_position = 'coop'");
 $stmt->execute([$electionId]);
 $election = $stmt->fetch();

if (!$election) {
    header('Location: admin_analytics_coop.php');
    exit();
}

// Determine if election is completed
 $now = new DateTime();
 $start = new DateTime($election['start_datetime']);
 $end = new DateTime($election['end_datetime']);
 $status = ($now < $start) ? 'upcoming' : (($now >= $start && $now <= $end) ? 'ongoing' : 'completed');

// ===== GET UNIQUE VOTERS WHO HAVE VOTED (not total votes) =====
 $sql = "SELECT COUNT(DISTINCT voter_id) as total FROM votes WHERE election_id = ?";
 $stmt = $pdo->prepare($sql);
 $stmt->execute([$electionId]);
 $totalVotesCast = $stmt->fetch()['total'];

// ===== GET ELIGIBLE VOTERS COUNT =====
 $conditions = ["role = 'voter'", "is_coop_member = 1", "migs_status = 1"];
 $params = [];

// Get allowed filters from election
 $allowed_status = array_filter(array_map('strtoupper', array_map('trim', explode(',', $election['allowed_status'] ?? ''))));

// Apply status filter if specified
if (!empty($allowed_status) && !in_array('ALL', $allowed_status)) {
    $placeholders = implode(',', array_fill(0, count($allowed_status), '?'));
    $conditions[] = "UPPER(status) IN ($placeholders)";
    $params = array_merge($params, $allowed_status);
}

// Build and execute the query for eligible voters
 $sql = "SELECT COUNT(*) as total FROM users WHERE " . implode(' AND ', $conditions);
 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $totalEligibleVoters = $stmt->fetch()['total'];

// Calculate turnout percentage
 $turnoutPercentage = ($totalEligibleVoters > 0) ? round(($totalVotesCast / $totalEligibleVoters) * 100, 1) : 0;

// ===== GET WINNERS BY POSITION =====
 $sql = "
    SELECT 
        ec.position,
        c.id as candidate_id,
        CONCAT(c.first_name, ' ', c.last_name) as candidate_name,
        COUNT(v.vote_id) as vote_count
    FROM election_candidates ec
    JOIN candidates c ON ec.candidate_id = c.id
    LEFT JOIN votes v ON ec.election_id = v.election_id 
                   AND ec.candidate_id = v.candidate_id
    WHERE ec.election_id = ?
    GROUP BY ec.position, c.id, c.first_name, c.last_name
    ORDER BY ec.position, vote_count DESC
";

 $stmt = $pdo->prepare($sql);
 $stmt->execute([$electionId]);
 $allCandidates = $stmt->fetchAll();

// Group by position and find winners
 $winnersByPosition = [];
foreach ($allCandidates as $candidate) {
    $position = $candidate['position'];
    if (!isset($winnersByPosition[$position])) {
        $winnersByPosition[$position] = [];
    }
    $winnersByPosition[$position][] = $candidate;
}

// For each position, determine winners (handle ties)
foreach ($winnersByPosition as $position => &$candidates) {
    if (empty($candidates)) continue;
    
    $maxVotes = $candidates[0]['vote_count'];
    $winners = [];
    
    foreach ($candidates as $candidate) {
        if ($candidate['vote_count'] == $maxVotes && $maxVotes > 0) {
            $winners[] = $candidate;
        } else {
            break;
        }
    }
    
    $candidates = $winners;
}

// ===== GET VOTER TURNOUT BREAKDOWN =====
 $sql = "
    SELECT 
        u.department,
        COUNT(DISTINCT u.user_id) as eligible_count,
        COUNT(DISTINCT v.voter_id) as voted_count
    FROM users u
    LEFT JOIN votes v ON u.user_id = v.voter_id AND v.election_id = ?
    WHERE u.role = 'voter' AND u.is_coop_member = 1 AND u.migs_status = 1
";

 $params = [$electionId];

// Apply status filter if specified
if (!empty($allowed_status) && !in_array('ALL', $allowed_status)) {
    $placeholders = implode(',', array_fill(0, count($allowed_status), '?'));
    $sql .= " AND UPPER(u.status) IN ($placeholders)";
    $params = array_merge($params, $allowed_status);
}

 $sql .= " GROUP BY u.department ORDER BY u.department";

 $stmt = $pdo->prepare($sql);
 $stmt->execute($params);
 $voterTurnoutData = $stmt->fetchAll();

// Calculate turnout percentage for each group
foreach ($voterTurnoutData as &$data) {
    $data['turnout_percentage'] = ($data['eligible_count'] > 0) ? 
        round(($data['voted_count'] / $data['eligible_count']) * 100, 1) : 0;
}

// Get list of statuses for dropdown
 $stmt = $pdo->query("SELECT DISTINCT status FROM users WHERE role = 'voter' AND is_coop_member = 1 AND migs_status = 1 AND status IS NOT NULL AND status != '' ORDER BY status");
 $statusesList = $stmt->fetchAll(PDO::FETCH_COLUMN);

 $pageTitle = 'COOP Election Analytics';

include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($election['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --cvsu-green-dark: #154734;
      --cvsu-green: #1E6F46;
      --cvsu-green-light: #37A66B;
      --cvsu-yellow: #FFD166;
    }
    .analytics-card {
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .analytics-card:hover {
      transform: translateY(-2px);
    }
    .winner-badge {
      background: linear-gradient(135deg, #FFD700, #FFA500);
      color: white;
    }
    
    /* Custom table styles */
    .data-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .data-table th, .data-table td {
      padding: 0.75rem;
      text-align: left;
    }
    
    .data-table th {
      background-color: #f3f4f6;
      font-weight: 600;
      color: #374151;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }
    
    .data-table td {
      border-bottom: 1px solid #e5e7eb;
    }
    
    .data-table tr:hover {
      background-color: #f9fafb;
    }
    
    .data-table .text-center {
      text-align: center;
    }
    
    .data-table .text-right {
      text-align: right;
    }
    
    .turnout-bar-container {
      width: 100%;
      height: 8px;
      background-color: #e5e7eb;
      border-radius: 4px;
      overflow: hidden;
    }
    
    .turnout-bar {
      height: 100%;
      border-radius: 4px;
    }
    
    .turnout-high {
      background-color: #10b981;
    }
    
    .turnout-medium {
      background-color: #f59e0b;
    }
    
    .turnout-low {
      background-color: #ef4444;
    }
    
    /* Custom scrollbar for table */
    .table-container::-webkit-scrollbar {
      height: 8px;
    }
    
    .table-container::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    
    .table-container::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 4px;
    }
    
    .table-container::-webkit-scrollbar-thumb:hover {
      background: #555;
    }
    
    /* Loading indicator */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s, visibility 0.3s;
    }
    
    .loading-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    
    .loading-spinner {
      width: 50px;
      height: 50px;
      border: 5px solid #f3f3f3;
      border-top: 5px solid var(--cvsu-green);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body class="bg-gray-50">
<div class="flex min-h-screen">
  
  <main class="flex-1 p-6 md:p-8 md:ml-64">
    <div class="max-w-7xl mx-auto">
      <!-- Election Information Header -->
      <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl shadow-xl overflow-hidden mb-8 border border-gray-100">
        <!-- Card Header -->
        <div class="bg-gradient-to-r from-[var(--cvsu-green-dark)] to-[var(--cvsu-green)] p-6 relative">
          <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-16 -mt-16"></div>
          <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-5 rounded-full -ml-12 -mb-12"></div>
          
          <div class="flex flex-col md:flex-row md:items-center md:justify-between relative z-10">
            <div class="flex-1">
              <div class="flex items-center mb-3">
                <div class="bg-white bg-opacity-20 p-3 rounded-xl mr-4 shadow-md">
                  <i class="fas fa-chart-line text-white text-3xl"></i>
                </div>
                <div>
                  <h1 class="text-3xl font-bold text-white leading-tight">
                    <?= htmlspecialchars($pageTitle) ?>
                  </h1>
                  <p class="text-green-100 text-lg font-medium">
                    <?= htmlspecialchars($election['title']) ?>
                  </p>
                </div>
              </div>
            </div>
            
            <div class="mt-4 md:mt-0 flex items-center space-x-3">
              <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold shadow-md
                    <?= $status === 'completed' ? 'bg-green-500 text-white' : 
                       ($status === 'ongoing' ? 'bg-blue-500 text-white' : 'bg-yellow-600 text-white') ?>">
                <?php if ($status === 'completed'): ?>
                  <i class="fas fa-check-circle mr-2"></i> Completed
                <?php elseif ($status === 'ongoing'): ?>
                  <i class="fas fa-clock mr-2"></i> Ongoing
                <?php else: ?>
                  <i class="fas fa-hourglass-start mr-2"></i> Upcoming
                <?php endif; ?>
              </span>
            </div>
          </div>
        </div>
        
        <!-- Card Body -->
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center">
                <div class="bg-green-50 p-3 rounded-xl mr-4">
                  <i class="fas fa-users text-green-600 text-2xl"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-500">Eligible Voters</p>
                  <p class="text-2xl font-bold text-gray-800"><?= number_format($totalEligibleVoters) ?></p>
                </div>
              </div>
            </div>
            
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center">
                <div class="bg-blue-50 p-3 rounded-xl mr-4">
                  <i class="fas fa-check-circle text-blue-600 text-2xl"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-500">Votes Cast</p>
                  <p class="text-2xl font-bold text-gray-800"><?= number_format($totalVotesCast) ?></p>
                </div>
              </div>
            </div>
            
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm">
              <div class="flex items-center">
                <div class="bg-purple-50 p-3 rounded-xl mr-4">
                  <i class="fas fa-percentage text-purple-600 text-2xl"></i>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-500">Turnout Rate</p>
                  <p class="text-2xl font-bold text-gray-800"><?= $turnoutPercentage ?>%</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Winners Section -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-800">
            <i class="fas fa-trophy text-yellow-500 mr-2"></i>Election Winners
          </h2>
        </div>
        
        <div class="p-6">
          <?php if (empty($winnersByPosition)): ?>
            <div class="text-center py-8">
              <i class="fas fa-users text-gray-400 text-4xl mb-3"></i>
              <p class="text-gray-600">No winners data available.</p>
            </div>
          <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <?php foreach ($winnersByPosition as $position => $winners): ?>
                <?php foreach ($winners as $winner): ?>
                  <div class="analytics-card bg-gradient-to-br from-yellow-50 to-white rounded-xl border border-yellow-200 p-6 shadow-sm">
                    <div class="flex items-center mb-4">
                      <div class="winner-badge w-12 h-12 rounded-full flex items-center justify-center font-bold text-lg mr-4">
                        <i class="fas fa-trophy"></i>
                      </div>
                      <div>
                        <h3 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($winner['candidate_name']) ?></h3>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($position) ?></p>
                      </div>
                    </div>
                    <div class="flex justify-between items-center">
                      <div>
                        <p class="text-2xl font-bold text-gray-800"><?= number_format($winner['vote_count']) ?></p>
                        <p class="text-sm text-gray-500">votes</p>
                      </div>
                      <?php if (count($winners) > 1): ?>
                        <span class="text-xs font-bold bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">TIE</span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Voter Turnout Analytics Section -->
      <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-gray-800">
            <i class="fas fa-chart-pie text-green-600 mr-2"></i>Voter Turnout Analytics
          </h2>
          <p class="text-sm text-gray-500 mt-1">COOP Election (MIGS members by college/department)</p>
        </div>
        
        <div class="p-6">
          <?php if (empty($voterTurnoutData)): ?>
            <div class="text-center py-8">
              <i class="fas fa-chart-bar text-gray-400 text-5xl mb-4"></i>
              <p class="text-gray-600 text-lg">No voter turnout data available.</p>
            </div>
          <?php else: ?>
            <!-- Filter Section -->
            <div class="mb-6">
              <!-- Status Selector -->
              <div class="mb-4 flex items-center justify-center">
                <label for="statusSelect" class="mr-3 text-sm font-medium text-gray-700">Select Status:</label>
                <select id="statusSelect" class="block w-64 px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                  <option value="all">All Statuses</option>
                  <?php foreach ($statusesList as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            
            <!-- Chart Section -->
            <div class="mb-12">
              <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">Turnout Visualization</h3>
              <div class="bg-gray-50 p-6 rounded-xl shadow-sm">
                <div class="h-96">
                  <canvas id="turnoutChart"></canvas>
                </div>
              </div>
            </div>
            
            <!-- Detailed Breakdown Section -->
            <div class="mt-12">
              <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">Detailed Breakdown</h3>
              <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto table-container">
                  <div id="tableContainer" class="w-full">
                    <!-- Table will be dynamically generated here -->
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Back Button -->
      <div class="mt-6">
        <a href="admin_analytics_coop.php" 
           class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
          <i class="fas fa-arrow-left mr-2"></i>
          Back to Election Analytics
        </a>
      </div>
    </div>
  </main>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
  <div class="loading-spinner"></div>
</div>

<script>
// Store all breakdown data
const breakdownData = {
  'department': <?= json_encode($voterTurnoutData) ?>
};

// Chart instance
let turnoutChartInstance = null;

// Current state
let currentState = {
  status: 'all'
};

document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded');
  
  // Initialize URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  currentState.status = urlParams.get('status') || 'all';
  
  // Set status dropdown value
  if (currentState.status !== 'all') {
    document.getElementById('statusSelect').value = currentState.status;
  }
  
  // Check if Chart.js is loaded
  if (typeof Chart === 'undefined') {
    console.error('Chart.js is not loaded!');
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    script.onload = function() {
      console.log('Chart.js loaded dynamically');
      updateView();
    };
    script.onerror = function() {
      console.error('Failed to load Chart.js');
      showNoDataMessage();
    };
    document.head.appendChild(script);
  } else {
    console.log('Chart.js is already loaded');
    updateView();
  }
  
  // Add event listener to status selector
  document.getElementById('statusSelect')?.addEventListener('change', function() {
    const selectedStatus = this.value;
    
    // Update state and URL
    updateState({ status: selectedStatus });
  });
  
  // Handle back/forward buttons
  window.addEventListener('popstate', function(event) {
    if (event.state) {
      currentState = event.state;
      
      // Update dropdowns
      document.getElementById('statusSelect').value = currentState.status;
      
      // Update view without showing loading
      updateView(false);
    }
  });
});

function updateState(newState) {
  // Show loading
  showLoading();
  
  // Update current state
  currentState = { ...currentState, ...newState };
  
  // Update URL without reloading the page
  const url = new URL(window.location);
  url.searchParams.set('status', currentState.status);
  
  // Push new state to history
  window.history.pushState(currentState, '', url);
  
  // Update view
  updateView();
}

function updateView(showLoading = true) {
  if (showLoading) {
    // Small delay to show loading indicator
    setTimeout(() => {
      const data = getFilteredData();
      updateChart(data, 'department');
      generateTable(data, 'department');
      hideLoading();
    }, 300);
  } else {
    const data = getFilteredData();
    updateChart(data, 'department');
    generateTable(data, 'department');
  }
}

function getFilteredData() {
  let data = breakdownData['department'];
  
  // Filter by status if specified
  if (currentState.status !== 'all') {
    // For COOP, we need to filter the original data by status
    // Since we don't have status in the breakdown data, we'll need to make an API call
    // For now, we'll just return all data
    // In a real implementation, you would fetch filtered data from the server
  }
  
  return data;
}

function updateChart(data, breakdownType) {
  const canvas = document.getElementById('turnoutChart');
  if (!canvas) {
    console.error('Canvas element not found!');
    return;
  }
  
  const ctx = canvas.getContext('2d');
  
  // Prepare labels
  const labels = data.map(item => item.department);
  
  // Prepare data
  const eligibleData = data.map(item => parseInt(item.eligible_count) || 0);
  const votedData = data.map(item => parseInt(item.voted_count) || 0);
  
  // Destroy existing chart if it exists
  if (turnoutChartInstance) {
    turnoutChartInstance.destroy();
  }
  
  // Calculate dynamic settings based on data count
  const dataCount = labels.length;
  
  // Adjust bar thickness and spacing based on data count
  let barThickness, categorySpacing, fontSize, maxBarThickness;
  
  if (dataCount <= 5) {
    // Few data points - thicker bars, larger text
    barThickness = 0.8;
    categorySpacing = 0.2;
    fontSize = 14;
    maxBarThickness = 80;
  } else if (dataCount <= 10) {
    // Medium data points - medium bars and text
    barThickness = 0.6;
    categorySpacing = 0.3;
    fontSize = 12;
    maxBarThickness = 60;
  } else if (dataCount <= 20) {
    // Many data points - thinner bars, smaller text
    barThickness = 0.4;
    categorySpacing = 0.4;
    fontSize = 10;
    maxBarThickness = 40;
  } else {
    // Very many data points - very thin bars, smallest text
    barThickness = 0.3;
    categorySpacing = 0.5;
    fontSize = 9;
    maxBarThickness = 30;
  }
  
  try {
    turnoutChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Eligible Voters',
            data: eligibleData,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            borderRadius: 4,
            barPercentage: barThickness,
            categoryPercentage: 1 - categorySpacing,
            maxBarThickness: maxBarThickness
          },
          {
            label: 'Voted',
            data: votedData,
            backgroundColor: 'rgba(75, 192, 192, 0.7)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1,
            borderRadius: 4,
            barPercentage: barThickness,
            categoryPercentage: 1 - categorySpacing,
            maxBarThickness: maxBarThickness
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
            labels: {
              font: {
                size: 14
              },
              padding: 20
            }
          },
          title: {
            display: true,
            text: 'Voter Turnout by College',
            font: {
              size: 18,
              weight: 'bold'
            },
            padding: {
              top: 10,
              bottom: 30
            }
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleFont: {
              size: 14
            },
            bodyFont: {
              size: 13
            },
            padding: 12,
            cornerRadius: 4,
            callbacks: {
              label: function(context) {
                let label = context.dataset.label || '';
                if (label) {
                  label += ': ';
                }
                if (context.parsed.y !== null) {
                  label += new Intl.NumberFormat('en-US', { 
                    style: 'decimal', 
                    maximumFractionDigits: 0 
                  }).format(context.parsed.y);
                }
                return label;
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              precision: 0,
              font: {
                size: 12
              }
            },
            grid: {
              color: 'rgba(0, 0, 0, 0.1)'
            },
            title: {
              display: true,
              text: 'Number of Voters',
              font: {
                size: 14,
                weight: 'bold'
              }
            }
          },
          x: {
            ticks: {
              font: {
                size: fontSize
              },
              maxRotation: 0, // Keep labels horizontal
              minRotation: 0,
              autoSkip: true,
              maxTicksLimit: 20 // Limit number of ticks shown
            },
            grid: {
              display: false
            },
            title: {
              display: true,
              text: 'College',
              font: {
                size: 14,
                weight: 'bold'
              }
            }
          }
        },
        animation: {
          duration: 1000,
          easing: 'easeOutQuart'
        },
        layout: {
          padding: {
            left: 10,
            right: 10,
            top: 10,
            bottom: 10
          }
        }
      }
    });
    
    console.log('Chart updated successfully!');
  } catch (error) {
    console.error('Error updating chart:', error);
    showNoDataMessage();
  }
}

function generateTable(data, breakdownType) {
  const tableContainer = document.getElementById('tableContainer');
  
  // Clear existing content
  tableContainer.innerHTML = '';
  
  // Create table element
  const table = document.createElement('table');
  table.className = 'data-table';
  
  // Create table header
  const thead = document.createElement('thead');
  const headerRow = document.createElement('tr');
  
  headerRow.innerHTML = `
    <th style="width: 40%">College</th>
    <th style="width: 20%" class="text-center">Eligible</th>
    <th style="width: 20%" class="text-center">Voted</th>
    <th style="width: 20%" class="text-center">Turnout %</th>
  `;
  
  thead.appendChild(headerRow);
  table.appendChild(thead);
  
  // Create table body
  const tbody = document.createElement('tbody');
  
  data.forEach(item => {
    const row = document.createElement('tr');
    
    row.innerHTML = `
      <td style="width: 40%">${item.department}</td>
      <td style="width: 20%" class="text-center">${numberFormat(item.eligible_count)}</td>
      <td style="width: 20%" class="text-center">${numberFormat(item.voted_count)}</td>
      <td style="width: 20%" class="text-center">${createTurnoutBar(item.turnout_percentage)}</td>
    `;
    
    tbody.appendChild(row);
  });
  
  table.appendChild(tbody);
  tableContainer.appendChild(table);
}

function createTurnoutBar(percentage) {
  // Determine color based on percentage
  let barColor = 'turnout-low'; // Low turnout
  if (percentage >= 70) {
    barColor = 'turnout-high'; // High turnout
  } else if (percentage >= 40) {
    barColor = 'turnout-medium'; // Medium turnout
  }
  
  return `
    <div class="flex flex-col items-center">
      <div class="turnout-bar-container w-32">
        <div class="turnout-bar ${barColor}" style="width: ${percentage}%"></div>
      </div>
      <span class="text-sm font-bold text-gray-700 mt-1">${percentage}%</span>
    </div>
  `;
}

function numberFormat(num) {
  return new Intl.NumberFormat('en-US').format(num);
}

function showLoading() {
  document.getElementById('loadingOverlay').classList.add('active');
}

function hideLoading() {
  document.getElementById('loadingOverlay').classList.remove('active');
}

function showNoDataMessage(message = 'No data available for chart') {
  const chartContainer = document.querySelector('#turnoutChart').parentElement;
  chartContainer.innerHTML = `
    <div class="flex items-center justify-center h-64 bg-gray-50 rounded-lg border border-gray-200">
      <div class="text-center">
        <i class="fas fa-chart-bar text-gray-400 text-4xl mb-3"></i>
        <p class="text-gray-600">${message}</p>
      </div>
    </div>
  `;
}
</script>
</body>
</html>