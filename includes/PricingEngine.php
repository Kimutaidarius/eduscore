<?php
class PricingEngine {
    private $dbh;
    private $school_id;
    private $school_info;
    private $billing_settings;
    
    // Pricing matrix
    private $pricing_matrix = [
        'primary' => [
            'public' => [
                'single_finance' => ['per_student' => 15, 'onboarding' => 2000],
                'single_exam' => ['per_student' => 15, 'onboarding' => 2000],
                'both' => ['per_student' => 25, 'onboarding' => 3500]
            ],
            'private' => [
                'single_finance' => ['per_student' => 30, 'onboarding' => 5000],
                'single_exam' => ['per_student' => 30, 'onboarding' => 5000],
                'both' => ['per_student' => 50, 'onboarding' => 8000]
            ]
        ],
        'secondary' => [
            'public' => [
                'single_finance' => ['per_student' => 20, 'onboarding' => 2500],
                'single_exam' => ['per_student' => 20, 'onboarding' => 2500],
                'both' => ['per_student' => 35, 'onboarding' => 4500]
            ],
            'private' => [
                'single_finance' => ['per_student' => 40, 'onboarding' => 6000],
                'single_exam' => ['per_student' => 40, 'onboarding' => 6000],
                'both' => ['per_student' => 70, 'onboarding' => 10000]
            ]
        ]
    ];
    
    public function __construct($dbh, $school_id) {
        $this->dbh = $dbh;
        $this->school_id = $school_id;
        $this->loadSchoolData();
    }
    
    private function loadSchoolData() {
        // Get school info
        $stmt = $this->dbh->prepare("SELECT * FROM tblschoolinfo WHERE id = ?");
        $stmt->execute([$this->school_id]);
        $this->school_info = $stmt->fetch();
        
        if (!$this->school_info) {
            // School not found, create default
            $this->school_info = ['id' => $this->school_id, 'school_name' => 'Unknown'];
        }
        
        // Get or create billing settings
        $stmt = $this->dbh->prepare("SELECT * FROM school_billing_settings WHERE school_id = ?");
        $stmt->execute([$this->school_id]);
        $this->billing_settings = $stmt->fetch();
        
        if (!$this->billing_settings) {
            $this->initializeBillingSettings();
        }
    }
    
    private function initializeBillingSettings() {
        // Determine school level based on classes in tblclasses
        // Your table uses 'academic_level' and 'class_level' columns
        $stmt = $this->dbh->prepare("
            SELECT DISTINCT 
                academic_level,
                class_level
            FROM tblclasses 
            WHERE school_id = ?
            LIMIT 1
        ");
        $stmt->execute([$this->school_id]);
        $class_info = $stmt->fetch();
        
        $school_level = 'primary'; // Default
        if ($class_info) {
            $academic_level = strtolower($class_info['academic_level'] ?? '');
            $class_level = strtolower($class_info['class_level'] ?? '');
            
            // Check if secondary (Forms 1-4)
            if (strpos($academic_level, 'secondary') !== false || 
                strpos($class_level, 'form') !== false ||
                preg_match('/form [1-4]/i', $class_level)) {
                $school_level = 'secondary';
            }
            // Check if primary (Grades 1-8 or Class 1-8)
            else if (strpos($academic_level, 'primary') !== false || 
                     strpos($class_level, 'grade') !== false ||
                     preg_match('/(grade|class) [1-8]/i', $class_level)) {
                $school_level = 'primary';
            }
        }
        
        // Determine school type from school info or default to public
        $school_type = 'public';
        if (isset($this->school_info['school_type'])) {
            $school_type = strtolower($this->school_info['school_type']);
        } elseif (isset($this->school_info['type'])) {
            $school_type = strtolower($this->school_info['type']);
        }
        
        // Determine package (default to both systems)
        $system_package = 'both';
        if (isset($this->school_info['system_package'])) {
            $system_package = $this->school_info['system_package'];
        }
        
        $pricing = $this->getPricing($school_level, $school_type, $system_package);
        
        // Check if billing settings table exists, create if not
        $this->ensureBillingSettingsTable();
        
        $stmt = $this->dbh->prepare("
            INSERT INTO school_billing_settings 
            (school_id, school_level, school_type, system_package, price_per_student, onboarding_fee)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->school_id,
            $school_level,
            $school_type,
            $system_package,
            $pricing['per_student'],
            $pricing['onboarding']
        ]);
        
        $this->loadSchoolData();
    }
    
    private function ensureBillingSettingsTable() {
        // Check if table exists
        $stmt = $this->dbh->prepare("SHOW TABLES LIKE 'school_billing_settings'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // Create the table
            $this->dbh->exec("
                CREATE TABLE IF NOT EXISTS school_billing_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    school_id INT NOT NULL,
                    school_level ENUM('primary', 'secondary') DEFAULT 'primary',
                    school_type ENUM('public', 'private') DEFAULT 'public',
                    system_package ENUM('single_finance', 'single_exam', 'both') DEFAULT 'both',
                    price_per_student DECIMAL(10,2),
                    onboarding_fee DECIMAL(10,2),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_school_billing (school_id),
                    INDEX idx_school (school_id)
                )
            ");
        }
    }
    
    public function getPricing($level = null, $type = null, $package = null) {
        $level = $level ?? ($this->billing_settings['school_level'] ?? 'primary');
        $type = $type ?? ($this->billing_settings['school_type'] ?? 'public');
        $package = $package ?? ($this->billing_settings['system_package'] ?? 'both');
        
        // Ensure the pricing exists in the matrix
        if (!isset($this->pricing_matrix[$level][$type][$package])) {
            // Fallback to primary public both
            return $this->pricing_matrix['primary']['public']['both'];
        }
        
        return $this->pricing_matrix[$level][$type][$package];
    }
    
    public function getStudentCount() {
        $stmt = $this->dbh->prepare("
            SELECT COUNT(*) as count 
            FROM tblstudents 
            WHERE school_id = ? AND Status = 'Active'
        ");
        $stmt->execute([$this->school_id]);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }
    
    public function calculateSubscriptionAmount() {
        $student_count = $this->getStudentCount();
        $pricing = $this->getPricing();
        
        return $student_count * $pricing['per_student'];
    }
    
    public function getOnboardingFee() {
        $pricing = $this->getPricing();
        return $pricing['onboarding'];
    }
    
    public function getPricePerStudent() {
        $pricing = $this->getPricing();
        return $pricing['per_student'];
    }
    
    public function getSchoolLevel() {
        return $this->billing_settings['school_level'] ?? 'primary';
    }
    
    public function getSchoolType() {
        return $this->billing_settings['school_type'] ?? 'public';
    }
    
    public function getSystemPackage() {
        return $this->billing_settings['system_package'] ?? 'both';
    }
    
    public function getPackageDisplayName() {
        $package = $this->getSystemPackage();
        switch($package) {
            case 'single_finance': return 'Single System (Finance)';
            case 'single_exam': return 'Single System (Exam)';
            case 'both': return 'Both Systems (Finance + Exam)';
            default: return 'Standard Package';
        }
    }
    
    public function updateBillingSettings($school_level, $school_type, $system_package) {
        $pricing = $this->getPricing($school_level, $school_type, $system_package);
        
        $stmt = $this->dbh->prepare("
            UPDATE school_billing_settings 
            SET school_level = ?, 
                school_type = ?, 
                system_package = ?,
                price_per_student = ?,
                onboarding_fee = ?,
                updated_at = NOW()
            WHERE school_id = ?
        ");
        return $stmt->execute([
            $school_level,
            $school_type,
            $system_package,
            $pricing['per_student'],
            $pricing['onboarding'],
            $this->school_id
        ]);
    }
}
?>