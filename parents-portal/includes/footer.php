<?php
// includes/footer.php - Common Footer
?>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const savedTheme = localStorage.getItem('parentDashboardTheme');
    if (savedTheme === 'dark') body.classList.add('dark-mode');
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            localStorage.setItem('parentDashboardTheme', body.classList.contains('dark-mode') ? 'dark' : 'light');
            const span = themeToggle.querySelector('span');
            if (span) span.textContent = body.classList.contains('dark-mode') ? 'Light' : 'Dark';
        });
    }
    
    // Scroll reveal animation
    const reveals = document.querySelectorAll('.reveal');
    const revealObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) entry.target.classList.add('active');
        });
    }, { threshold: 0.1 });
    reveals.forEach(function(el) {
        revealObserver.observe(el);
    });
});

// Global filter functions
function changeStudent(studentId) {
    const url = new URL(window.location.href);
    url.searchParams.set('student_id', studentId);
    url.searchParams.delete('exam_id');
    window.location.href = url.toString();
}

function changeExam() {
    const examId = document.getElementById('examSelect').value;
    const url = new URL(window.location.href);
    if (examId && examId != 0) {
        url.searchParams.set('exam_id', examId);
    } else {
        url.searchParams.delete('exam_id');
    }
    window.location.href = url.toString();
}

function changeFilters() {
    const year = document.getElementById('yearSelect').value;
    const termId = document.getElementById('termSelect').value;
    const url = new URL(window.location.href);
    url.searchParams.set('year', year);
    url.searchParams.set('term_id', termId);
    url.searchParams.delete('exam_id');
    window.location.href = url.toString();
}

function selectExam(examId) {
    const url = new URL(window.location.href);
    url.searchParams.set('exam_id', examId);
    window.location.href = url.toString();
}
</script>
</body>
</html>