<?php
require_once 'database.php';
$conn = Database::getInstance()->getConnection();
$stmt = $conn->query("SELECT exam_id, title, status, teacher_institution, class_section FROM exams WHERE status='active'");
$activeExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Aktif Sınavlar</h3>";
echo "<table border='1'><tr><th>ID</th><th>Başlık</th><th>Kurum (teacher_institution)</th><th>Sınıf (class_section)</th></tr>";
foreach ($activeExams as $exam) {
    echo "<tr><td>{$exam['exam_id']}</td><td>{$exam['title']}</td><td>{$exam['teacher_institution']}</td><td>{$exam['class_section']}</td></tr>";
}
echo "</table>";

$stmt = $conn->query("SELECT DISTINCT branch FROM users WHERE role='student'");
$branches = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<h3>Öğrenci Şubeleri</h3>";
echo implode(", ", $branches);

$stmt = $conn->query("SELECT DISTINCT class_section FROM users WHERE role='student'");
$sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<h3>Öğrenci Sınıfları</h3>";
echo implode(", ", $sections);
