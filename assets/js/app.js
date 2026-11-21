/**
 * نظام إدارة الموظفين - ملف JavaScript الرئيسي
 * Employee Management System - Main JavaScript File
 */

// انتظار تحميل الصفحة بالكامل
document.addEventListener('DOMContentLoaded', function() {
    // تفعيل التوافيقية للجداول
    const tables = document.querySelectorAll('.data-table');
    if (tables.length > 0) {
        // تحميل DataTables إذا كانت الجداول موجودة
        loadScript('https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js', function() {
            loadCSS('https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css');
            loadCSS('https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css');

            tables.forEach(table => {
                $(table).DataTable({
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/ar.json'
                    },
                    responsive: true,
                    pageLength: 10
                });
            });
        });
    }

    // تفعيل أدوات التأكيد قبل الحذف
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const itemName = this.getAttribute('data-item-name') || 'هذا العنصر';

            if (confirm(`هل أنت متأكد من حذف ${itemName}؟ لا يمكن التراجع عن هذه العملية.`)) {
                window.location.href = this.getAttribute('href');
            }
        });
    });

    // تفعيل أدوات تحديد التاريخ
    const dateInputs = document.querySelectorAll('.date-picker');
    if (dateInputs.length > 0) {
        loadScript('https://cdn.jsdelivr.net/npm/flatpickr', function() {
            loadCSS('https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');
            loadCSS('https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css');

            dateInputs.forEach(input => {
                flatpickr(input, {
                    locale: 'ar',
                    dateFormat: 'Y-m-d',
                    maxDate: 'today'
                });
            });
        });
    }

    // تفعيل معاينة الصور قبل الرفع
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = input.parentElement.querySelector('.image-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.className = 'image-preview mt-2';
                        input.parentElement.appendChild(preview);
                    }
                    preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-height: 200px;">`;
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // تفعيل نظام الإشعارات
    const notificationContainer = document.createElement('div');
    notificationContainer.className = 'notification-container position-fixed top-0 end-0 p-3';
    notificationContainer.style.zIndex = '1050';
    document.body.appendChild(notificationContainer);

    // وظيفة عرض الإشعارات
    window.showNotification = function(message, type = 'info') {
        const alertClass = type === 'success' ? 'alert-success' : 
                          type === 'error' ? 'alert-danger' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';

        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        notificationContainer.appendChild(notification);

        // إغلاق الإشعار تلقائيًا بعد 5 ثوانٍ
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    };

    // تفعيل رسائل التأكيد
    const successMessages = document.querySelectorAll('.success-message');
    successMessages.forEach(msg => {
        showNotification(msg.textContent, 'success');
    });

    const errorMessages = document.querySelectorAll('.error-message');
    errorMessages.forEach(msg => {
        showNotification(msg.textContent, 'error');
    });

    // تفعيل أزرار التحميل
    const loadingButtons = document.querySelectorAll('.loading-btn');
    loadingButtons.forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>جاري التحميل...';
            this.disabled = true;

            // استعادة الزر بعد 5 ثوانٍ كحد أقصى
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 5000);
        });
    });
});

// وظائف مساعدة

// تحميل ملف JavaScript
function loadScript(src, callback) {
    const script = document.createElement('script');
    script.src = src;
    script.onload = callback;
    document.head.appendChild(script);
}

// تحميل ملف CSS
function loadCSS(href) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
}

// تنسيق التاريخ
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA');
}

// تأكيد قبل تنفيذ إجراء
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// نسخ النص إلى الحافظة
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('تم نسخ النص إلى الحافظة', 'success');
    }).catch(err => {
        showNotification('فشل نسخ النص: ' + err, 'error');
    });
}

// طباعة المحتوى
function printContent(elementId) {
    const printContents = document.getElementById(elementId).innerHTML;
    const originalContents = document.body.innerHTML;

    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;

    // إعادة تفعيل الأحداث بعد الطباعة
    location.reload();
}
