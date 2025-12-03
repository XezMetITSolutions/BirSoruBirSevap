<?php
require_once 'database.php';

// Increase time limit for migration
set_time_limit(300);

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "Starting migration...\n";
    
    // 1. Migrate Exams
    $examsFile = 'data/exams.json';
    if (file_exists($examsFile)) {
        $exams = json_decode(file_get_contents($examsFile), true) ?? [];
        echo "Found " . count($exams) . " exams in JSON.\n";
        
        foreach ($exams as $examCode => $exam) {
            try {
                // Prepare data
                $examId = $exam['id'] ?? $examCode;
                $title = $exam['title'] ?? 'Untitled Exam';
                $description = $exam['description'] ?? '';
                $createdBy = $exam['teacher_id'] ?? 'admin'; // Default fallback
                $teacherName = $exam['teacher_name'] ?? '';
                $teacherInstitution = $exam['teacher_section'] ?? ''; // Mapping 'teacher_section' to institution
                $classSection = $exam['class_section'] ?? '';
                $duration = (int)($exam['duration'] ?? 30);
                $questionCount = (int)($exam['question_count'] ?? 0);
                $questions = json_encode($exam['questions'] ?? [], JSON_UNESCAPED_UNICODE);
                $categories = json_encode($exam['categories'] ?? [], JSON_UNESCAPED_UNICODE);
                $status = $exam['status'] ?? 'active';
                $scheduleType = $exam['schedule_type'] ?? 'immediate';
                
                // Handle dates
                $startDate = !empty($exam['start_date']) ? date('Y-m-d H:i:s', strtotime($exam['start_date'])) : null;
                $endDate = !empty($exam['end_date']) ? date('Y-m-d H:i:s', strtotime($exam['end_date'])) : null;
                $scheduledStart = !empty($exam['scheduled_start']) ? date('Y-m-d H:i:s', strtotime($exam['scheduled_start'])) : null;
                $createdAt = !empty($exam['created_at']) ? date('Y-m-d H:i:s', strtotime($exam['created_at'])) : date('Y-m-d H:i:s');
                
                // Insert or Update
                $sql = "INSERT INTO exams (
                            exam_id, created_by, title, description, teacher_name, teacher_institution, 
                            class_section, duration, question_count, questions, categories, status, 
                            schedule_type, start_date, end_date, scheduled_start, created_at
                        ) VALUES (
                            :exam_id, :created_by, :title, :description, :teacher_name, :teacher_institution,
                            :class_section, :duration, :question_count, :questions, :categories, :status,
                            :schedule_type, :start_date, :end_date, :scheduled_start, :created_at
                        ) ON DUPLICATE KEY UPDATE
                            title = VALUES(title),
                            description = VALUES(description),
                            questions = VALUES(questions),
                            categories = VALUES(categories),
                            status = VALUES(status),
                            updated_at = CURRENT_TIMESTAMP";
                            
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':exam_id' => $examId,
                    ':created_by' => $createdBy,
                    ':title' => $title,
                    ':description' => $description,
                    ':teacher_name' => $teacherName,
                    ':teacher_institution' => $teacherInstitution,
                    ':class_section' => $classSection,
                    ':duration' => $duration,
                    ':question_count' => $questionCount,
                    ':questions' => $questions,
                    ':categories' => $categories,
                    ':status' => $status,
                    ':schedule_type' => $scheduleType,
                    ':start_date' => $startDate,
                    ':end_date' => $endDate,
                    ':scheduled_start' => $scheduledStart,
                    ':created_at' => $createdAt
                ]);
                
                echo "Migrated exam: $title ($examId)\n";
                
            } catch (Exception $e) {
                echo "Error migrating exam $examCode: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "No exams.json found.\n";
    }
    
    echo "Migration completed.\n";
    
} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage() . "\n";
}
