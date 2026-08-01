/**
 * assets/js/app.js
 * Shared front-end behavior across pages. Page-specific DataTables
 * config lives inline in view.php for now; move shared bits here as
 * they emerge (e.g. a common date-range picker, toast helper).
 */

document.addEventListener('DOMContentLoaded', () => {
    // Auto-dismiss flash alerts after 5s
    document.querySelectorAll('.alert').forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.4s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 5000);
    });
});