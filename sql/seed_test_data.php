<?php
/**
 * Test Platform Database Seeder
 * 
 * Creates comprehensive sample data:
 *   - 5 Colleges (with unique code, nick_name, full address)
 *   - 5 College Streams & 5 College Batches (for College Wizard/Dashboard)
 *   - 5 Courses & 5 Batches (for Signup / Assessment assignments)
 *   - 50 Students (10 per batch, password: student123)
 *   - 5 Active Tests (with test_sections mapped)
 *   - 25 MCQ Questions (5 per test)
 *   - 50 Submissions & Evaluated Answers (with auto_score, total_score)
 *   - 50 PCI Records
 *
 * Can be run:
 *   1. CLI: php sql/seed_test_data.php [--fresh]
 *   2. Admin Web UI: Settings -> Load Seed Data
 *   3. Auto-seed via environment variable: AUTO_SEED=true
 */

require_once __DIR__ . '/../src/php/config/db.php';

/**
 * Executes the database seeding routine.
 * 
 * @param PDO  $pdo        Active PDO connection
 * @param bool $clearFirst Whether to truncate existing sample data first
 * @return array           Summary counts of created records
 */
function runDatabaseSeeder(PDO $pdo, bool $clearFirst = false): array {
    $stats = [
        'colleges'        => 0,
        'streams'         => 0,
        'college_batches' => 0,
        'courses'         => 0,
        'batches'         => 0,
        'students'        => 0,
        'tests'           => 0,
        'questions'       => 0,
        'submissions'     => 0,
        'answers'         => 0,
        'pci_records'     => 0,
    ];

    // Ensure super admin exists before doing anything
    $admin = $pdo->query("SELECT id FROM admins ORDER BY id ASC LIMIT 1")->fetch();
    if (!$admin) {
        $adminPasswordHash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmtAdmin = $pdo->prepare("INSERT INTO admins (email, name, password_hash, role) VALUES (?, ?, ?, ?)");
        $stmtAdmin->execute(['admin@testplatform.com', 'Administrator', $adminPasswordHash, 'super_admin']);
        $adminId = (int)$pdo->lastInsertId();
    } else {
        $adminId = (int)$admin['id'];
    }

    if ($clearFirst) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = [
            'pci_records', 'tab_switch_logs', 'student_answers', 'submissions',
            'questions', 'test_sections', 'tests', 'students', 'unverified_students',
            'batches', 'courses', 'college_batches', 'college_streams', 'colleges'
        ];
        foreach ($tables as $t) {
            try {
                $pdo->exec("DELETE FROM `{$t}`");
                $pdo->exec("ALTER TABLE `{$t}` AUTO_INCREMENT = 1");
            } catch (Throwable $ignored) {}
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }

    $pdo->beginTransaction();

    try {
        $passwordHash = password_hash('student123', PASSWORD_BCRYPT);

        // ─── 1. COLLEGES ─────────────────────────────────────────
        $collegeDefs = [
            [
                'code'      => 'COL000001',
                'nick'      => 'SRIT',
                'name'      => 'Sri Ramachandra Institute of Technology',
                'address'   => 'Porur, Chennai, Tamil Nadu',
                'city'      => 'Chennai',
                'state'     => 'Tamil Nadu',
                'est_year'  => 1995,
                'stream'    => 'Bachelor of Technology (BTech)',
                'course'    => 'B.Tech Computer Science & Engineering',
                'branch'    => 'Computer Science',
                'batch_nick'=> 'SRIT_CSE_2024_08',
                'batch_name'=> 'CSE Batch 2026',
                'domain'    => 'srit.edu',
            ],
            [
                'code'      => 'COL000002',
                'nick'      => 'SJCE',
                'name'      => 'St. Joseph\'s College of Engineering',
                'address'   => 'OMR, Chennai, Tamil Nadu',
                'city'      => 'Chennai',
                'state'     => 'Tamil Nadu',
                'est_year'  => 1994,
                'stream'    => 'Bachelor of Engineering (BE)',
                'course'    => 'B.Tech Information Science & Engineering',
                'branch'    => 'Information Science',
                'batch_nick'=> 'SJCE_ISE_2024_08',
                'batch_name'=> 'ISE Batch 2026',
                'domain'    => 'sjce.edu',
            ],
            [
                'code'      => 'COL000003',
                'nick'      => 'BMSCE',
                'name'      => 'BMS College of Engineering',
                'address'   => 'Bull Temple Road, Bangalore, Karnataka',
                'city'      => 'Bangalore',
                'state'     => 'Karnataka',
                'est_year'  => 1946,
                'stream'    => 'Bachelor of Engineering (BE)',
                'course'    => 'B.Tech Electronics & Communication',
                'branch'    => 'Electronics',
                'batch_nick'=> 'BMSCE_ECE_2024_08',
                'batch_name'=> 'ECE Batch 2026',
                'domain'    => 'bmsce.edu',
            ],
            [
                'code'      => 'COL000004',
                'nick'      => 'PESIT',
                'name'      => 'PES Institute of Technology',
                'address'   => '100 Feet Ring Road, Bangalore, Karnataka',
                'city'      => 'Bangalore',
                'state'     => 'Karnataka',
                'est_year'  => 1988,
                'stream'    => 'Bachelor of Technology (BTech)',
                'course'    => 'B.Tech Mechanical Engineering',
                'branch'    => 'Mechanical',
                'batch_nick'=> 'PESIT_ME_2024_08',
                'batch_name'=> 'ME Batch 2026',
                'domain'    => 'pes.edu',
            ],
            [
                'code'      => 'COL000005',
                'nick'      => 'RVCE',
                'name'      => 'RV College of Engineering',
                'address'   => 'Mysore Road, Bangalore, Karnataka',
                'city'      => 'Bangalore',
                'state'     => 'Karnataka',
                'est_year'  => 1963,
                'stream'    => 'Bachelor of Engineering (BE)',
                'course'    => 'B.Tech Artificial Intelligence & ML',
                'branch'    => 'AIML',
                'batch_nick'=> 'RVCE_AIML_2024_08',
                'batch_name'=> 'AIML Batch 2026',
                'domain'    => 'rvce.edu',
            ],
        ];

        $stmtCol = $pdo->prepare("
            INSERT INTO colleges (
                college_code, nick_name, name, address, city, state, country,
                established_year, status, accreditation_naac, naac_grade
            ) VALUES (?, ?, ?, ?, ?, ?, 'India', ?, 'active', 1, 'A++')
        ");

        $stmtStream = $pdo->prepare("
            INSERT INTO college_streams (college_id, stream_name, category)
            VALUES (?, ?, 'UGC')
        ");

        $stmtColBatch = $pdo->prepare("
            INSERT INTO college_batches (
                college_id, stream_id, academic_year, joining_year, joining_month,
                course_duration, ending_year, batch_nick_name, status
            ) VALUES (?, ?, '2024-2028', 2024, 8, 4, 2028, ?, 'active')
        ");

        $stmtCourse = $pdo->prepare("
            INSERT INTO courses (college_id, name, status)
            VALUES (?, ?, 'active')
        ");

        $stmtBatch = $pdo->prepare("
            INSERT INTO batches (course_id, name, section, status)
            VALUES (?, ?, 'A', 'active')
        ");

        $createdEntities = [];

        foreach ($collegeDefs as $idx => $c) {
            // Check if college already exists
            $existing = $pdo->prepare("SELECT id FROM colleges WHERE nick_name = ? OR name = ?");
            $existing->execute([$c['nick'], $c['name']]);
            $colRow = $existing->fetch();

            if ($colRow) {
                $collegeId = (int)$colRow['id'];
            } else {
                $stmtCol->execute([$c['code'], $c['nick'], $c['name'], $c['address'], $c['city'], $c['state'], $c['est_year']]);
                $collegeId = (int)$pdo->lastInsertId();
                $stats['colleges']++;
            }

            // Stream
            $existingStream = $pdo->prepare("SELECT id FROM college_streams WHERE college_id = ? AND stream_name = ?");
            $existingStream->execute([$collegeId, $c['stream']]);
            $sRow = $existingStream->fetch();
            if ($sRow) {
                $streamId = (int)$sRow['id'];
            } else {
                $stmtStream->execute([$collegeId, $c['stream']]);
                $streamId = (int)$pdo->lastInsertId();
                $stats['streams']++;
            }

            // College Batch
            $existingCb = $pdo->prepare("SELECT id FROM college_batches WHERE batch_nick_name = ?");
            $existingCb->execute([$c['batch_nick']]);
            $cbRow = $existingCb->fetch();
            if ($cbRow) {
                $cbId = (int)$cbRow['id'];
            } else {
                $stmtColBatch->execute([$collegeId, $streamId, $c['batch_nick']]);
                $cbId = (int)$pdo->lastInsertId();
                $stats['college_batches']++;
            }

            // Course
            $existingCourse = $pdo->prepare("SELECT id FROM courses WHERE college_id = ? AND name = ?");
            $existingCourse->execute([$collegeId, $c['course']]);
            $crsRow = $existingCourse->fetch();
            if ($crsRow) {
                $courseId = (int)$crsRow['id'];
            } else {
                $stmtCourse->execute([$collegeId, $c['course']]);
                $courseId = (int)$pdo->lastInsertId();
                $stats['courses']++;
            }

            // Batch
            $existingBatch = $pdo->prepare("SELECT id FROM batches WHERE course_id = ? AND name = ?");
            $existingBatch->execute([$courseId, $c['batch_name']]);
            $bRow = $existingBatch->fetch();
            if ($bRow) {
                $batchId = (int)$bRow['id'];
            } else {
                $stmtBatch->execute([$courseId, $c['batch_name']]);
                $batchId = (int)$pdo->lastInsertId();
                $stats['batches']++;
            }

            $createdEntities[] = [
                'college_id' => $collegeId,
                'stream_id'  => $streamId,
                'course_id'  => $courseId,
                'batch_id'   => $batchId,
                'college'    => $c,
            ];
        }

        // ─── 2. STUDENTS (10 per batch = 50 total) ─────────────
        $firstNames = ['Aarav','Vivaan','Aditya','Vihaan','Arjun','Sai','Ishaan','Ayaan','Dhruv','Reyansh',
                       'Ananya','Diya','Myra','Sara','Aadhya','Avni','Ira','Anaya','Jiya','Kavya',
                       'Rohan','Rahul','Ravi','Sunil','Manoj','Vijay','Rajesh','Nitin','Deepak','Arun',
                       'Priya','Neha','Pooja','Ritu','Anjali','Sneha','Kiran','Lata','Meena','Rekha',
                       'Akash','Bharat','Chandan','Dinesh','Eknath','Farhan','Gaurav','Hitesh','Irfan','Jatin'];

        $lastNames = ['Sharma','Verma','Patel','Reddy','Kumar','Singh','Gupta','Joshi','Nair','Menon',
                      'Desai','Iyer','Rao','Murthy','Shetty','Naidu','Choudhury','Banerjee','Mukherjee','Das',
                      'Pillai','Thakur','More','Pawar','Khanna','Kapoor','Mehta','Seth','Bajaj','Agarwal',
                      'Saxena','Trivedi','Upadhyay','Dwivedi','Chaturvedi','Pandey','Mishra','Tiwari','Dubey','Shukla',
                      'Nayak','Jena','Swain','Patnaik','Mohanty','Padhy','Sahoo','Behera','Das','Pradhan'];

        $studentData = [];
        $studentIdx = 0;

        $stmtStudent = $pdo->prepare("
            INSERT INTO students (
                batch_id, name, phone, email, gender, college_name, branch,
                roll_number, year_of_joining, course_name, password_hash
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2024, ?, ?)
        ");

        foreach ($createdEntities as $bIndex => $ent) {
            $batchId     = $ent['batch_id'];
            $collegeName = $ent['college']['name'];
            $courseName  = $ent['college']['course'];
            $branchName  = $ent['college']['branch'];
            $domain      = $ent['college']['domain'];

            for ($s = 0; $s < 10; $s++) {
                $idx = $studentIdx++;
                $name  = $firstNames[$idx] . ' ' . $lastNames[$idx];
                $phone = '98765' . str_pad((string)mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
                $email = 'student' . ($idx + 1) . '@' . $domain;
                $gender = ($idx < 15 || ($idx >= 20 && $idx < 35)) ? 'male' : 'female';
                $roll   = strtoupper(substr($branchName, 0, 3)) . str_pad((string)($idx + 1), 4, '0', STR_PAD_LEFT);

                // Check duplicate email
                $chkStudent = $pdo->prepare("SELECT id FROM students WHERE email = ?");
                $chkStudent->execute([$email]);
                $stRow = $chkStudent->fetch();

                if ($stRow) {
                    $stId = (int)$stRow['id'];
                } else {
                    $stmtStudent->execute([$batchId, $name, $phone, $email, $gender, $collegeName, $branchName, $roll, $courseName, $passwordHash]);
                    $stId = (int)$pdo->lastInsertId();
                    $stats['students']++;
                }

                $studentData[] = [
                    'id'     => $stId,
                    'name'   => $name,
                    'email'  => $email,
                    'batch_idx' => $bIndex,
                    'batch_id'  => $batchId,
                ];
            }
        }

        // ─── 3. TESTS (1 per batch = 5 total) ──────────────────
        $testTitles = [
            'CSE Core Fundamentals',
            'Data Structures & Algorithms',
            'Digital Electronics Basics',
            'Thermodynamics & Fluids',
            'Introduction to Machine Learning',
        ];

        $stmtTest = $pdo->prepare("
            INSERT INTO tests (
                batch_id, title, description, duration_minutes,
                start_time, end_time, status, max_tab_switches,
                shuffle_questions, passing_marks, total_marks, created_by
            ) VALUES (?, ?, ?, 30, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active', 5, 1, 4, 10, ?)
        ");

        $stmtTs = $pdo->prepare("
            INSERT IGNORE INTO test_sections (test_id, batch_id) VALUES (?, ?)
        ");

        $testData = [];

        foreach ($createdEntities as $tIdx => $ent) {
            $batchId = $ent['batch_id'];
            $title   = $testTitles[$tIdx];

            $chkTest = $pdo->prepare("SELECT id FROM tests WHERE batch_id = ? AND title = ?");
            $chkTest->execute([$batchId, $title]);
            $tRow = $chkTest->fetch();

            if ($tRow) {
                $testId = (int)$tRow['id'];
            } else {
                $stmtTest->execute([$batchId, $title, 'Assessment for ' . $ent['college']['batch_name'], $adminId]);
                $testId = (int)$pdo->lastInsertId();
                $stats['tests']++;
            }

            $stmtTs->execute([$testId, $batchId]);

            $testData[] = [
                'id'       => $testId,
                'batch_id' => $batchId,
                'title'    => $title,
            ];
        }

        // ─── 4. QUESTIONS (5 MCQ per test = 25 total) ───────────
        $mcqQuestions = [
            [   // CSE Core
                ['q' => 'Which data structure uses LIFO principle?',
                 'opts' => [['key'=>'A','text'=>'Queue'],['key'=>'B','text'=>'Stack'],['key'=>'C','text'=>'Array'],['key'=>'D','text'=>'Tree'],['key'=>'E','text'=>'Graph']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'What is the time complexity of binary search?',
                 'opts' => [['key'=>'A','text'=>'O(n)'],['key'=>'B','text'=>'O(log n)'],['key'=>'C','text'=>'O(n²)'],['key'=>'D','text'=>'O(1)'],['key'=>'E','text'=>'O(n log n)']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'Which of the following is a relational database?',
                 'opts' => [['key'=>'A','text'=>'MongoDB'],['key'=>'B','text'=>'Redis'],['key'=>'C','text'=>'MySQL'],['key'=>'D','text'=>'Neo4j'],['key'=>'E','text'=>'Cassandra']],
                 'ans' => 'C', 'marks' => 2],
                ['q' => 'What does CPU stand for?',
                 'opts' => [['key'=>'A','text'=>'Central Process Unit'],['key'=>'B','text'=>'Computer Personal Unit'],['key'=>'C','text'=>'Central Processing Unit'],['key'=>'D','text'=>'Core Processing Unit'],['key'=>'E','text'=>'Central Program Unit']],
                 'ans' => 'C', 'marks' => 2],
                ['q' => 'Which protocol is used for web browsing?',
                 'opts' => [['key'=>'A','text'=>'FTP'],['key'=>'B','text'=>'SMTP'],['key'=>'C','text'=>'HTTP'],['key'=>'D','text'=>'TCP'],['key'=>'E','text'=>'UDP']],
                 'ans' => 'C', 'marks' => 2],
            ],
            [   // DSA
                ['q' => 'Which sorting algorithm has the best average-case time complexity?',
                 'opts' => [['key'=>'A','text'=>'Bubble Sort'],['key'=>'B','text'=>'Quick Sort'],['key'=>'C','text'=>'Selection Sort'],['key'=>'D','text'=>'Insertion Sort'],['key'=>'E','text'=>'Merge Sort']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'What is a hash table used for?',
                 'opts' => [['key'=>'A','text'=>'Sorting data'],['key'=>'B','text'=>'Fast data retrieval'],['key'=>'C','text'=>'Graph traversal'],['key'=>'D','text'=>'File compression'],['key'=>'E','text'=>'Encryption']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'Which tree traversal visits left → root → right?',
                 'opts' => [['key'=>'A','text'=>'Preorder'],['key'=>'B','text'=>'Postorder'],['key'=>'C','text'=>'Inorder'],['key'=>'D','text'=>'Level order'],['key'=>'E','text'=>'Reverse order']],
                 'ans' => 'C', 'marks' => 2],
                ['q' => 'What is the worst-case time complexity of Quick Sort?',
                 'opts' => [['key'=>'A','text'=>'O(n)'],['key'=>'B','text'=>'O(log n)'],['key'=>'C','text'=>'O(n log n)'],['key'=>'D','text'=>'O(n²)'],['key'=>'E','text'=>'O(1)']],
                 'ans' => 'D', 'marks' => 2],
                ['q' => 'Which data structure is used for BFS?',
                 'opts' => [['key'=>'A','text'=>'Stack'],['key'=>'B','text'=>'Queue'],['key'=>'C','text'=>'Array'],['key'=>'D','text'=>'Heap'],['key'=>'E','text'=>'Linked List']],
                 'ans' => 'B', 'marks' => 2],
            ],
            [   // Digital Electronics
                ['q' => 'What is the binary equivalent of decimal 10?',
                 'opts' => [['key'=>'A','text'=>'1010'],['key'=>'B','text'=>'1100'],['key'=>'C','text'=>'1001'],['key'=>'D','text'=>'1110'],['key'=>'E','text'=>'1000']],
                 'ans' => 'A', 'marks' => 2],
                ['q' => 'Which gate outputs 1 only when both inputs are 1?',
                 'opts' => [['key'=>'A','text'=>'OR'],['key'=>'B','text'=>'XOR'],['key'=>'C','text'=>'NAND'],['key'=>'D','text'=>'AND'],['key'=>'E','text'=>'NOR']],
                 'ans' => 'D', 'marks' => 2],
                ['q' => 'How many bits are in a byte?',
                 'opts' => [['key'=>'A','text'=>'4'],['key'=>'B','text'=>'8'],['key'=>'C','text'=>'16'],['key'=>'D','text'=>'32'],['key'=>'E','text'=>'64']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'Which flip-flop is edge-triggered?',
                 'opts' => [['key'=>'A','text'=>'SR Latch'],['key'=>'B','text'=>'JK Flip-Flop'],['key'=>'C','text'=>'D Latch'],['key'=>'D','text'=>'Gated SR'],['key'=>'E','text'=>'Transparent Latch']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'What is the hexadecimal equivalent of 255?',
                 'opts' => [['key'=>'A','text'=>'FF'],['key'=>'B','text'=>'F0'],['key'=>'C','text'=>'EF'],['key'=>'D','text'=>'FE'],['key'=>'E','text'=>'AA']],
                 'ans' => 'A', 'marks' => 2],
            ],
            [   // Thermodynamics
                ['q' => 'What is the SI unit of temperature?',
                 'opts' => [['key'=>'A','text'=>'Celsius'],['key'=>'B','text'=>'Fahrenheit'],['key'=>'C','text'=>'Kelvin'],['key'=>'D','text'=>'Rankine'],['key'=>'E','text'=>'Newton']],
                 'ans' => 'C', 'marks' => 2],
                ['q' => 'Which law states energy cannot be created or destroyed?',
                 'opts' => [['key'=>'A','text'=>'Newton\'s First Law'],['key'=>'B','text'=>'Zeroth Law'],['key'=>'C','text'=>'First Law of Thermodynamics'],['key'=>'D','text'=>'Second Law'],['key'=>'E','text'=>'Third Law']],
                 'ans' => 'C', 'marks' => 2],
                ['q' => 'What is entropy a measure of?',
                 'opts' => [['key'=>'A','text'=>'Temperature'],['key'=>'B','text'=>'Pressure'],['key'=>'C','text'=>'Disorder'],['key'=>'D','text'=>'Volume'],['key'=>'E','text'=>'Density']],
                 'ans' => 'C', 'marks' => 2],
                ['q' => 'Which process occurs at constant pressure?',
                 'opts' => [['key'=>'A','text'=>'Isothermal'],['key'=>'B','text'=>'Isochoric'],['key'=>'C','text'=>'Isobaric'],['key'=>'D','text'=>'Adiabatic'],['key'=>'E','text'=>'Isentropic']],
                 'ans' => 'C', 'marks' => 2],
                ['q' => 'What is the efficiency of a Carnot engine dependent on?',
                 'opts' => [['key'=>'A','text'=>'Working fluid'],['key'=>'B','text'=>'Temperatures'],['key'=>'C','text'=>'Engine size'],['key'=>'D','text'=>'Fuel type'],['key'=>'E','text'=>'Pressure ratio']],
                 'ans' => 'B', 'marks' => 2],
            ],
            [   // ML
                ['q' => 'Which algorithm is used for classification?',
                 'opts' => [['key'=>'A','text'=>'Linear Regression'],['key'=>'B','text'=>'Logistic Regression'],['key'=>'C','text'=>'K-Means'],['key'=>'D','text'=>'PCA'],['key'=>'E','text'=>'Apriori']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'What is overfitting?',
                 'opts' => [['key'=>'A','text'=>'Model underperforms on training data'],['key'=>'B','text'=>'Model memorizes training data, fails on new data'],['key'=>'C','text'=>'Model is too simple'],['key'=>'D','text'=>'Model trains too fast'],['key'=>'E','text'=>'Model needs more data']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'Which is a supervised learning algorithm?',
                 'opts' => [['key'=>'A','text'=>'K-Means Clustering'],['key'=>'B','text'=>'Decision Tree'],['key'=>'C','text'=>'PCA'],['key'=>'D','text'=>'Apriori'],['key'=>'E','text'=>'t-SNE']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'What does CNN stand for?',
                 'opts' => [['key'=>'A','text'=>'Convergent Neural Network'],['key'=>'B','text'=>'Convolutional Neural Network'],['key'=>'C','text'=>'Central Neural Network'],['key'=>'D','text'=>'Connected Neural Network'],['key'=>'E','text'=>'Complex Neural Network']],
                 'ans' => 'B', 'marks' => 2],
                ['q' => 'Which metric is used for regression evaluation?',
                 'opts' => [['key'=>'A','text'=>'Accuracy'],['key'=>'B','text'=>'Precision'],['key'=>'C','text'=>'Recall'],['key'=>'D','text'=>'Mean Squared Error'],['key'=>'E','text'=>'F1 Score']],
                 'ans' => 'D', 'marks' => 2],
            ],
        ];

        $questionIds = [];
        $stmtQ = $pdo->prepare("
            INSERT INTO questions (test_id, type, question_text, options_json, correct_answer, marks, sort_order)
            VALUES (?, 'mcq', ?, ?, ?, ?, ?)
        ");

        for ($t = 0; $t < 5; $t++) {
            $testId = $testData[$t]['id'];
            $questions = $mcqQuestions[$t];
            $questionIds[$t] = [];

            // Check if questions already exist for this test
            $existingQ = $pdo->prepare("SELECT id FROM questions WHERE test_id = ? ORDER BY sort_order ASC");
            $existingQ->execute([$testId]);
            $existingRows = $existingQ->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($existingRows)) {
                $questionIds[$t] = $existingRows;
            } else {
                foreach ($questions as $qi => $q) {
                    $stmtQ->execute([
                        $testId,
                        $q['q'],
                        json_encode($q['opts']),
                        $q['ans'],
                        $q['marks'],
                        $qi + 1
                    ]);
                    $questionIds[$t][] = (int)$pdo->lastInsertId();
                    $stats['questions']++;
                }
            }
        }

        // ─── 5. SUBMISSIONS + ANSWERS ───────────────────────────
        $stmtSub = $pdo->prepare("
            INSERT INTO submissions (
                student_id, test_id, status, evaluation_status, started_at, submitted_at,
                total_marks_obtained, total_marks, auto_score, total_score
            ) VALUES (?, ?, 'evaluated', 'evaluated', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), ?, 10, ?, ?)
        ");

        $stmtAns = $pdo->prepare("
            INSERT INTO student_answers (
                submission_id, question_id, answer_json, marks_obtained, is_auto_graded, evaluated_at
            ) VALUES (?, ?, ?, ?, 1, NOW())
        ");

        $stmtPci = $pdo->prepare("
            INSERT INTO pci_records (
                student_id, test_id, pci_score, mcq_score, coding_score,
                explanation_score, mcq_weight, coding_weight, explanation_weight
            ) VALUES (?, ?, ?, ?, 0, 0, 100, 0, 0)
        ");

        foreach ($studentData as $sd) {
            $tIdx   = $sd['batch_idx'];
            $testId = $testData[$tIdx]['id'];
            $questions = $mcqQuestions[$tIdx];
            $qIds      = $questionIds[$tIdx];

            // Check if submission already exists
            $chkSub = $pdo->prepare("SELECT id FROM submissions WHERE student_id = ? AND test_id = ?");
            $chkSub->execute([$sd['id'], $testId]);
            if ($chkSub->fetch()) {
                continue;
            }

            $obtained = 0;
            $answers  = [];

            foreach ($questions as $qi => $q) {
                $marks = $q['marks'];
                $isCorrect = (mt_rand(1, 100) <= 70); // 70% passing chance
                if ($isCorrect) {
                    $selected = $q['ans'];
                    $obtained += $marks;
                } else {
                    $wrongOptions = array_filter($q['opts'], fn($o) => $o['key'] !== $q['ans']);
                    $wrongKeys = array_map(fn($o) => $o['key'], $wrongOptions);
                    $selected = $wrongKeys[array_rand($wrongKeys)];
                }

                $answers[] = [
                    'qid'    => $qIds[$qi] ?? 0,
                    'answer' => json_encode(['selected' => $selected]),
                    'marks'  => $isCorrect ? $marks : 0,
                ];
            }

            $stmtSub->execute([$sd['id'], $testId, $obtained, $obtained, $obtained]);
            $submissionId = (int)$pdo->lastInsertId();
            $stats['submissions']++;

            foreach ($answers as $a) {
                if ($a['qid'] > 0) {
                    $stmtAns->execute([$submissionId, $a['qid'], $a['answer'], $a['marks']]);
                    $stats['answers']++;
                }
            }

            // PCI Record
            $chkPci = $pdo->prepare("SELECT id FROM pci_records WHERE student_id = ? AND test_id = ?");
            $chkPci->execute([$sd['id'], $testId]);
            if (!$chkPci->fetch()) {
                $pci = round(mt_rand(4000, 9500) / 100, 2);
                $stmtPci->execute([$sd['id'], $testId, $pci, $pci]);
                $stats['pci_records']++;
            }
        }

        $pdo->commit();
        return $stats;

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ─── CLI Entrypoint ──────────────────────────────────────────────
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "====================================================\n";
    echo "  LMS / Test Platform - Database Seed Script\n";
    echo "====================================================\n\n";

    $clearFirst = in_array('--fresh', $argv ?? []) || in_array('--clear', $argv ?? []);
    if ($clearFirst) {
        echo "⚠️  --fresh flag passed: Truncating existing test data...\n";
    }

    try {
        $pdo = getDB();
        $stats = runDatabaseSeeder($pdo, $clearFirst);

        echo "\n✅ Database seeded successfully!\n";
        echo "----------------------------------------------------\n";
        echo "   Colleges Created:        {$stats['colleges']}\n";
        echo "   Streams Created:         {$stats['streams']}\n";
        echo "   College Batches:         {$stats['college_batches']}\n";
        echo "   Courses Created:         {$stats['courses']}\n";
        echo "   Batches Created:         {$stats['batches']}\n";
        echo "   Students Created:        {$stats['students']}\n";
        echo "   Tests Created:           {$stats['tests']}\n";
        echo "   Questions Created:       {$stats['questions']}\n";
        echo "   Submissions Created:     {$stats['submissions']}\n";
        echo "   Answers Recorded:        {$stats['answers']}\n";
        echo "   PCI Records Created:     {$stats['pci_records']}\n";
        echo "----------------------------------------------------\n";
        echo "🔑 Super Admin Login:\n";
        echo "   Email:    admin@testplatform.com\n";
        echo "   Password: admin123\n\n";
        echo "👨‍🎓 Student Logins (50 total):\n";
        echo "   student1@srit.edu  ... student10@srit.edu  (SRIT)\n";
        echo "   student11@sjce.edu ... student20@sjce.edu (SJCE)\n";
        echo "   student21@bmsce.edu... student30@bmsce.edu(BMSCE)\n";
        echo "   student31@pes.edu  ... student40@pes.edu  (PESIT)\n";
        echo "   student41@rvce.edu ... student50@rvce.edu (RVCE)\n";
        echo "   Password for all students: student123\n";
        echo "====================================================\n";
        exit(0);
    } catch (Throwable $e) {
        echo "❌ Seeding failed: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
        exit(1);
    }
}
