<?php
require_once 'database.php';
require_once 'ExamManager.php';

$username = $_GET['username'] ?? '';
if (empty($username)) {
    die("Kullanıcı adı gerekli (?username=...)");
}

$conn = Database::getInstance()->getConnection();
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Öğrenci Bilgileri</h3>";
echo "<pre>" . print_r($user, true) . "</pre>";

$studentInstitution = $user['branch'] ?? $user['institution'] ?? '';
$studentClass = $user['class_section'] ?? '';

echo "<h3>Eşleşen Kurum/Sınıf</h3>";
echo "Kurum (branch): " . $studentInstitution . "<br>";
echo "Sınıf (class_section): " . $studentClass . "<br>";

$examManager = new ExamManager();
$stmt = $conn->prepare("SELECT exam_id, title, status, teacher_institution, class_section FROM exams WHERE status != 'inactive'");
$stmt->execute();
$allExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Sistemdeki Tüm Aktif Sınavlar</h3>";
echo "<table border='1'><tr><th>ID</th><th>Başlık</th><th>Durum</th><th>Kurum (teacher_institution)</th><th>Sınıf (class_section)</th><th>Eşleşiyor mu?</th></tr>";

$norm = function($s){ return mb_strtolower(trim((string)$s), 'UTF-8'); };
$si = $norm($studentInstitution);
$sc = $norm($studentClass);

foreach ($allExams as $exam) {
    $examClassSection = $exam['class_section'] ?? '';
    $examInstitution = $exam['teacher_institution'] ?? '';
    
    $es = $norm($examClassSection);
    $ei = $norm($examInstitution);
    
    $match = ($es !== '' && ($es === $si || $es === $sc)) ||
             ($ei !== '' && ($ei === $si || $ei === $sc));
             
    echo "<tr>";
    echo "<td>" . $exam['exam_id'] . "</td>";
    echo "<td>" . $exam['title'] . "</td>";
    echo "<td>" . $exam['status'] . "</td>";
    echo "<td>" . $examInstitution . "</td>";
    echo "<td>" . $examClassSection . "</td>";
    echo "<td>" . ($match ? "EVET ✅" : "HAYIR ❌") . "</td>";
    echo "</tr>";
}
echo "</table>";
