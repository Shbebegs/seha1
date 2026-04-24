/*
 * ملف: script.js (الكود المصلح والكامل)
 * المهام: معالجة التفاعلات على الواجهة الأمامية (الجافاسكريبت).
 * - التعامل مع طلبات AJAX لإدارة الأطباء، المرضى، الإجازات، وسجل الاستعلامات.
 * - تحديث الجداول والإحصائيات ديناميكيًا.
 * - وظائف البحث، الفرز، والفلترة بدون إعادة تحميل الصفحة.
 * - إدارة الوضع الفاتح/الداكن.
 * - عرض رسائل التنبيه (Toasts) ومؤشرات التحميل.
 * - وظائف الطباعة والتصدير (PDF, Excel).
 * - إصلاح الأخطاء وتضمين الأجزاء الناقصة.
 */

// ======================== إعدادات عامة ومتغيرات DOM ========================
// هنا يتم تعريف المتغيرات الأولية في JavaScript
// The following variables must be defined in a <script> tag in your HTML before this file is loaded:
// initialLeavesData, initialArchivedData, initialQueriesData, initialDoctorsData, initialPatientsData, initialPaymentsData, initialNotificationsData
// Example:
// <script>
//   const initialLeavesData = <?php echo json_encode($leaves); ?>;
//   ...
// </script>

document.addEventListener('DOMContentLoaded', () => {
    // ─── متغيّر عالمي ودالة عرض استعلامات المودال ───
    let currentDetailQueries = [];

    function renderDetailQueries(arr) {
        if (!arr.length) {
            queriesDetailsContainer.innerHTML =
                '<p class="text-center">لا توجد سجلات استعلام لهذه الإجازة.</p>';
            return;
        }
        queriesDetailsContainer.innerHTML =
            '<ul class="list-group" id="detailedQueriesList"></ul>';
        const list = document.getElementById('detailedQueriesList');
        arr.forEach(q => {
            const li = document.createElement('li');
            li.className =
                'list-group-item d-flex justify-content-between align-items-center';
            li.setAttribute('data-id', q.id || q.qid);
            li.innerHTML = `
        <span>${htmlspecialchars(q.queried_at)}</span>
        <button class="btn btn-danger btn-sm btn-delete-detail-query" data-id="${q.id || q.qid}">
          <i class="bi bi-trash-fill"></i> حذف
        </button>
      `;
            list.appendChild(li);
        });
    }
    // ────────────────────────────────────────────────

    // تعريف دالة htmlspecialchars في JavaScript (مهم جداً لإصلاح أخطاء ReferenceError)
    function htmlspecialchars(str) {
        if (typeof str !== 'string') return str;
        return str.replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // تمرير البيانات الأولية من PHP إلى JavaScript
    // يجب أن تقوم بتضمين هذه المتغيرات في جزء PHP من ملفك هكذا (قبل استدعاء هذا السكربت):
    // <script>
    // const initialLeavesData = <?= json_encode($leaves) ?>;
    // const initialArchivedData = <?= json_encode($archived) ?>;
    // const initialQueriesData = <?= json_encode($queries) ?>;
    // const initialDoctorsData = <?= json_encode($doctors) ?>;
    // const initialPatientsData = <?= json_encode($patients) ?>;
    // const initialPaymentsData = <?= json_encode($payments) ?>;
    // const initialNotificationsData = <?= json_encode($notifications_payment) ?>;
    // </script>
    const initialLeaves = window.initialLeavesData || [];
    const initialArchived = window.initialArchivedData || [];
    const initialQueries = window.initialQueriesData || [];
    const initialDoctors = window.initialDoctorsData || [];
    const initialPatients = window.initialPatientsData || [];
    const initialPayments = window.initialPaymentsData || [];
    const initialNotifications = window.initialNotificationsData || [];
    /* Removed duplicate declaration of currentDetailQueries */

    // تحديد عناصر DOM الرئيسية
    const leaveForm = document.getElementById('leaveForm');
    const editLeaveForm = document.getElementById('editLeaveForm');
    const doctorsTable = document.getElementById('doctorsTable');
    const patientsTable = document.getElementById('patientsTable');
    const leavesTable = document.getElementById('leavesTable');
    const archivedTable = document.getElementById('archivedTable');
    const queriesTable = document.getElementById('queriesTable');
    const paymentsTable = document.getElementById('paymentsTable');

    const patientSelect = document.getElementById('patient_select');
    const patientManualName = document.getElementById('patient_manual_name');
    const patientManualId = document.getElementById('patient_manual_id');
    const patientManualPhone = document.getElementById('patient_manual_phone');
    const searchPatientInput = document.getElementById('searchPatient');
    const noPatientResult = document.getElementById('noPatientResult');

    const doctorSelect = document.getElementById('doctor_select');
    const doctorManualName = document.getElementById('doctor_manual_name');
    const doctorManualTitle = document.getElementById('doctor_manual_title');
    const doctorManualNote = document.getElementById('doctor_manual_note');
    const doctorSavedTitle = document.getElementById('doctor_saved_title');
    const doctorSavedNote = document.getElementById('doctor_saved_note');
    const searchDoctorInput = document.getElementById('searchDoctor');
    const noDoctorResult = document.getElementById('noDoctorResult');

    const issueDateInput = document.getElementById('issue_date');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const daysCountInput = document.getElementById('days_count');
    const daysManualCheckbox = document.getElementById('days_manual');

    const companionCheckbox = document.getElementById('is_companion');
    const companionFields = document.querySelectorAll('.companion-fields');
    const companionNameInput = document.getElementById('companion_name');
    const companionRelationInput = document.getElementById('companion_relation');

    const serviceCodeManualInput = document.getElementById('service_code_manual');
    const servicePrefixSelect = document.getElementById('service_prefix');

    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmYesBtn = document.getElementById('confirmYesBtn');

    const viewQueriesModal = new bootstrap.Modal(document.getElementById('viewQueriesModal'));
    const queriesDetailsContainer = document.getElementById('queriesDetailsContainer');

    const paymentNotifModal = new bootstrap.Modal(document.getElementById('paymentNotifModal'));
    const notifPaymentsList = document.getElementById('notifPayments');

    const leaveDetailsModal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
    const payConfirmModal = new bootstrap.Modal(document.getElementById('confirmPayModal'));
    // عند الضغط على زر تأكيد الدفع في المودال
document.getElementById('confirmPayBtn').addEventListener('click', async () => {
  if (currentConfirmAction) {
    await currentConfirmAction();    // ينفّذ الـ AJAX اللي حددته سابقاً
  }
  payConfirmModal.hide();           // يخفي المودال بعد التنفيذ
});


    const leaveDetailsContainer = document.getElementById('leaveDetailsContainer');

    const doctorsModal = new bootstrap.Modal(document.getElementById('doctorsModal'));
    const patientsModal = new bootstrap.Modal(document.getElementById('patientsModal'));
    const editLeaveModal = new bootstrap.Modal(document.getElementById('editLeaveModal'));

    const loadingOverlay = document.getElementById('loadingOverlay');
    const alertContainer = document.getElementById('alert-container');

    let currentConfirmAction = null;
    let currentConfirmId = null;
    let currentTableData = {
        leaves: [],
        archived: [],
        queries: [],
        doctors: [],
        patients: [],
        payments: [],
        notifications_payment: []
    }; // لتخزين البيانات الحالية للجداول

    // ======================== دوال مساعدة (Helper Functions) ========================

    /**
     * يعرض رسالة تنبيه (Toast) للمستخدم.
     * @param {string} message - نص الرسالة.
     * @param {'success'|'danger'|'info'|'warning'} type - نوع الرسالة لتحديد اللون.
     */
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        alertContainer.append(toast);
        const bsToast = new bootstrap.Toast(toast, {
            delay: 5000
        });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    /**
     * يعرض مؤشر التحميل.
     */
    function showLoading() {
        loadingOverlay.classList.add('show');
    }

    /**
     * يخفي مؤشر التحميل.
     */
    function hideLoading() {
        loadingOverlay.classList.remove('show');
    }

    /**
     * يرسل طلب AJAX إلى الخادم.
     * @param {string} action - الإجراء المطلوب تنفيذه في السيرفر.
     * @param {FormData | URLSearchParams | object} data - البيانات المراد إرسالها.
     * @returns {Promise<object>} - وعد (Promise) بالاستجابة من السيرفر.
     */
    async function sendAjaxRequest(action, data) {
        showLoading();
        let formData;
        if (data instanceof FormData) {
            formData = data;
        } else if (data instanceof URLSearchParams) {
            formData = data; // يمكن استخدامها مباشرة إذا كانت URLSearchParams
        } else {
            formData = new FormData();
            for (const key in data) {
                formData.append(key, data[key]);
            }
        }
        formData.append('action', action); // إضافة الإجراء إلى البيانات

        // إضافة CSRF token
        const csrfToken = document.querySelector('input[name="csrf_token"]');
        if (csrfToken) {
            formData.append('csrf_token', csrfToken.value);
        }

        try {
            const response = await fetch('admin.php', { // التأكد من اسم الملف الصحيح
                method: 'POST',
                body: formData,
            });
            if (!response.ok) {
                // إذا لم تكن الاستجابة OK (مثل 404, 500)، ألقِ خطأ
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }
            const result = await response.json();
            if (!result.success) {
                showToast(result.message || 'حدث خطأ غير معروف.', 'danger');
            }
            return result;
        } catch (error) {
            console.error('AJAX request failed:', error);
            showToast('فشل في الاتصال بالخادم. يرجى المحاولة لاحقاً.', 'danger');
            return {
                success: false,
                message: 'فشل في الاتصال بالخادم.'
            };
        } finally {
            hideLoading();
        }
    }

    /**
     * يقوم بتحديث الأرقام الإحصائية في لوحة التحكم.
     * @param {object} stats - كائن يحتوي على الإحصائيات الجديدة.
     */
    function updateStats(stats) {
        if (!stats) return;

        document.querySelector('.stats-box:nth-child(1) br').nextSibling.textContent = stats.total;
        document.querySelector('.stats-box:nth-child(2) br').nextSibling.textContent = stats.active;
        document.querySelector('.stats-box:nth-child(3) br').nextSibling.textContent = stats.archived;
        document.querySelector('.stats-box:nth-child(4) br').nextSibling.textContent = stats.patients;
        document.querySelector('.stats-box:nth-child(5) br').nextSibling.textContent = stats.doctors;
        document.querySelector('.stats-box:nth-child(6) br').nextSibling.textContent = stats.paid;
        document.querySelector('.stats-box:nth-child(7) br').nextSibling.textContent = stats.unpaid;
        document.querySelector('.stats-box:nth-child(8) br').nextSibling.textContent = parseFloat(stats.paid_amount).toFixed(2);
        document.querySelector('.stats-box:nth-child(9) br').nextSibling.textContent = parseFloat(stats.unpaid_amount).toFixed(2);
    }

    /**
     * يحدّث أرقام الصفوف في جدول معين.
     * @param {HTMLTableElement} table - عنصر الجدول.
     */
    function updateRowNumbers(table) {
        const rows = table.querySelectorAll('tbody tr:not(.no-results)');
        rows.forEach((row, index) => {
            const numCell = row.querySelector('.row-num');
            if (numCell) {
                numCell.textContent = index + 1;
            }
        });
    }

    /**
     * يحسب عدد الأيام بين تاريخين.
     * @param {string} start - تاريخ البداية (YYYY-MM-DD).
     * @param {string} end - تاريخ النهاية (YYYY-MM-DD).
     * @returns {number} - عدد الأيام، 0 إذا كان التاريخ غير صالح.
     */
    function calculateDays(start, end) {
        if (!start || !end) return 0;
        const startDate = new Date(start + 'T00:00:00'); // إضافة وقت لتجنب مشاكل التوقيت
        const endDate = new Date(end + 'T00:00:00');     // إضافة وقت لتجنب مشاكل التوقيت
        if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || endDate < startDate) return 0;
        const diffTime = Math.abs(endDate - startDate);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 لتضمين يوم البداية والنهاية
        return diffDays;
    }

    /**
     * لمسح جميع حقول النموذج.
     * @param {HTMLFormElement} form - عنصر النموذج.
     */
    function clearForm(form) {
        form.reset();
        form.classList.remove('was-validated');
        // إخفاء الحقول اليدوية والتأكد من إعدادات days_count
        form.querySelectorAll('.hidden-field').forEach(el => {
            el.style.display = 'none';
            // إزالة حالة required من الحقول المخفية لضمان صحة التحقق
            if (el.matches('input, select, textarea')) {
                el.removeAttribute('required');
            }
        });

        if (form.id === 'leaveForm') {
            daysManualCheckbox.checked = false;
            daysCountInput.readOnly = true;
            companionCheckbox.checked = false;
            companionFields.forEach(field => { // استخدام forEach للوصول لكل عنصر
                const inputElement = field.querySelector('input, textarea');
                field.style.display = 'none';
                if (inputElement) inputElement.removeAttribute('required');
            });
            patientSelect.value = '';
            doctorSelect.value = '';
            searchPatientInput.value = '';
            searchDoctorInput.value = '';
            noPatientResult.style.display = 'none';
            noDoctorResult.style.display = 'none';
            doctorSavedTitle.value = '';
            doctorSavedNote.value = '';
        } else if (form.id === 'editLeaveForm') {
            document.getElementById('days_manual_edit').checked = false;
            document.getElementById('days_count_edit').readOnly = true;
            document.getElementById('is_companion_edit').checked = false;
            document.querySelectorAll('.companion-fields-edit').forEach(field => { // استخدام forEach للوصول لكل عنصر
                const inputElement = field.querySelector('input, textarea');
                field.style.display = 'none';
                if (inputElement) inputElement.removeAttribute('required');
            });
        } else if (form.id === 'doctorForm' || form.id === 'patientForm') {
            form.style.display = 'none';
            // إزالة required من حقول النموذج المخفية عند الإلغاء
            form.querySelectorAll('[required]').forEach(input => input.removeAttribute('required'));
        }
    }

    /**
     * يقوم بتحديث عنصر tbody في جدول معين بالبيانات الجديدة.
     * @param {HTMLTableElement} tableElement - عنصر الجدول.
     * @param {Array<object>} data - مصفوفة البيانات الجديدة.
     * @param {function(object): string} rowGenerator - دالة تولّد HTML لصف واحد.
     */
    function updateTable(tableElement, data, rowGenerator) {
        const tbody = tableElement.querySelector('tbody');
        tbody.innerHTML = ''; // مسح المحتوى الحالي

        if (data.length === 0) {
            const colspan = tableElement.querySelector('thead tr').children.length;
            tbody.innerHTML = `<tr class="no-results"><td colspan="${colspan}">لا توجد نتائج مطابقة.</td></tr>`;
            return;
        }

        data.forEach((item) => {
            tbody.insertAdjacentHTML('beforeend', rowGenerator(item));
        });
        updateRowNumbers(tableElement);
    }

    /**
     * للتحقق من صحة النموذج.
     * @param {HTMLFormElement} form - النموذج المراد التحقق منه.
     * @returns {boolean} - true إذا كان النموذج صالحًا، false بخلاف ذلك.
     */
    function validateForm(form) {
        let isValid = true;
        // إزالة رسائل التحقق السابقة
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        form.querySelectorAll('[required]').forEach(input => {
            // تحقق فقط من الحقول المرئية وغير المقروءة
            if (input.offsetParent !== null && !input.readOnly && !input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        // التحقق من صلاحية التواريخ
        if (form.id === 'leaveForm' || form.id === 'editLeaveForm') {
            const startDateInputEl = form.querySelector('[name*="start_date"]');
            const endDateInputEl = form.querySelector('[name*="end_date"]');

            if (startDateInputEl && endDateInputEl) {
                const startDate = new Date(startDateInputEl.value);
                const endDate = new Date(endDateInputEl.value);

                if (endDate < startDate) {
                    endDateInputEl.classList.add('is-invalid');
                    showToast('تاريخ نهاية الإجازة يجب أن يكون بعد أو يساوي تاريخ البداية.', 'danger');
                    isValid = false;
                } else {
                    endDateInputEl.classList.remove('is-invalid');
                }
            }
        }
        return isValid;
    }

    // ======================== CSRF Token Management ========================
    // هذه الدالة ستستدعى عند تحميل الصفحة أو عندما تحتاج إلى توكن جديد
    async function refreshCsrfToken() {
        const result = await sendAjaxRequest('get_csrf_token', {});
        if (result.success && result.csrf_token) {
            document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                input.value = result.csrf_token;
            });
        }
    }
    // لا تحتاج لاستدعائها هنا لأن PHP يولدها تلقائياً عند تحميل الصفحة،
    // ويتم إرسالها مع كل طلب AJAX تلقائياً داخل sendAjaxRequest.

    // ======================== وظائف إدارة الأطباء ========================

    /**
     * تجلب وتحدث قائمة الأطباء في المودال وفي قائمة select.
     * @param {string} selectedId - معرف الطبيب الذي يجب تحديده بعد التحديث (للتعديل).
     */
    async function fetchDoctors(selectedId = null) {
        const result = await sendAjaxRequest('fetch_all_doctors', {});
        if (result.success) {
            currentTableData.doctors = result.doctors; // تحديث البيانات المخزنة محليا
            updateTable(doctorsTable, result.doctors, generateDoctorRow);

            // تحديث قائمة الأطباء في نموذج إضافة/تعديل الإجازة
            doctorSelect.innerHTML = '<option value="">اختر طبيبًا</option><option value="manual">إدخال يدوي</option>';
            result.doctors.forEach(d => {
                const option = document.createElement('option');
                option.value = d.id;
                option.textContent = `${d.name} - ${d.title}`;
                option.dataset.name = d.name.toLowerCase();
                option.dataset.title = d.title.toLowerCase();
                option.dataset.note = (d.note || '').toLowerCase();
                doctorSelect.append(option);
            });
            if (selectedId) {
                doctorSelect.value = selectedId;
                toggleDoctorManualFields(); // لإظهار تفاصيل الطبيب بعد التحديد
            } else {
                doctorSelect.value = ''; // إذا لم يكن هناك طبيب محدد، عد إلى الخيار الأول
                toggleDoctorManualFields();
            }
        }
    }

    /**
     * تولّد صف HTML لبيانات الطبيب.
     * @param {object} d - بيانات الطبيب.
     * @returns {string} - HTML لصف الطبيب.
     */
    function generateDoctorRow(d) {
        return `
            <tr data-id="${d.id}">
                <td class="row-num"></td>
                <td class="cell-doctor-name">${htmlspecialchars(d.name)}</td>
                <td class="cell-doctor-title">${htmlspecialchars(d.title)}</td>
                <td class="cell-doctor-note">${htmlspecialchars(d.note || '')}</td>
                <td>
                    <button class="btn btn-warning btn-sm action-btn btn-edit-doctor"><i class="bi bi-pencil-square"></i> تعديل</button>
                    <button class="btn btn-danger btn-sm action-btn btn-delete-doctor"><i class="bi bi-trash-fill"></i> حذف</button>
                </td>
            </tr>
        `;
    }

    // ======================== وظائف إدارة المرضى ========================

    /**
     * تجلب وتحدث قائمة المرضى في المودال وفي قائمة select.
     * @param {string} selectedId - معرف المريض الذي يجب تحديده بعد التحديث (للتعديل).
     */
    async function fetchPatients(selectedId = null) {
        const result = await sendAjaxRequest('fetch_all_patients', {});
        if (result.success) {
            currentTableData.patients = result.patients; // تحديث البيانات المخزنة محليا
            updateTable(patientsTable, result.patients, generatePatientRow);

            // تحديث قائمة المرضى في نموذج إضافة/تعديل الإجازة
            patientSelect.innerHTML = '<option value="">اختر مريضًا</option><option value="manual">إدخال يدوي</option>';
            result.patients.forEach(p => {
                const option = document.createElement('option');
                option.value = p.id;
                option.textContent = `${p.name} (${p.identity_number})`;
                option.dataset.name = p.name.toLowerCase();
                option.dataset.identity = p.identity_number.toLowerCase();
                option.dataset.phone = (p.phone || '').toLowerCase();
                patientSelect.append(option);
            });
            if (selectedId) {
                patientSelect.value = selectedId;
                togglePatientManualFields(); // لإخفاء الحقول اليدوية إذا تم اختيار مريض موجود
            } else {
                patientSelect.value = ''; // إذا لم يكن هناك مريض محدد، عد إلى الخيار الأول
                togglePatientManualFields();
            }
        }
    }

    /**
     * تولّد صف HTML لبيانات المريض.
     * @param {object} p - بيانات المريض.
     * @returns {string} - HTML لصف المريض.
     */
    function generatePatientRow(p) {
        return `
            <tr data-id="${p.id}">
                <td class="row-num"></td>
                <td class="cell-patient-name">${htmlspecialchars(p.name)}</td>
                <td class="cell-patient-identity">${htmlspecialchars(p.identity_number)}</td>
                <td class="cell-patient-phone">${htmlspecialchars(p.phone || '')}</td>
                <td>
                    <button class="btn btn-warning btn-sm action-btn btn-edit-patient"><i class="bi bi-pencil-square"></i> تعديل</button>
                    <button class="btn btn-danger btn-sm action-btn btn-delete-patient"><i class="bi bi-trash-fill"></i> حذف</button>
                </td>
            </tr>
        `;
    }

    // ======================== وظائف إدارة الإجازات ========================

    /**
     * تجلب وتحدّث بيانات الإجازات (النشطة والأرشيف).
     */
    async function fetchAllLeaves() {
        showLoading();
        const response = await fetch('admin.php', { // التأكد من اسم الملف الصحيح
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'fetch_all_leaves', // إجراء افتراضي لجلب كل شيء
                csrf_token: document.querySelector('input[name="csrf_token"]').value
            })
        });
        const data = await response.json();
        hideLoading();

        if (data.success) {
            // تحديث البيانات المحلية
            currentTableData.leaves = data.leaves;
            currentTableData.archived = data.archived;
            currentTableData.queries = data.queries;
            currentTableData.notifications_payment = data.notifications_payment;
            currentTableData.payments = data.payments; // تحديث جدول المدفوعات لكل مريض أيضاً

            // تحديث الجداول
            updateTable(leavesTable, currentTableData.leaves, generateLeaveRow);
            updateTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow);
            updateTable(queriesTable, currentTableData.queries, generateQueryRow);
            updateTable(paymentsTable, currentTableData.payments, generatePaymentPatientRow); // تحديث جدول المدفوعات
            updatePaymentNotifications(currentTableData.notifications_payment);
            updateStats(data.stats);

        } else {
            showToast(data.message || 'فشل في جلب بيانات الإجازات.', 'danger');
        }
    }


    /**
     * تولّد صف HTML لبيانات الإجازة النشطة.
     * @param {object} lv - بيانات الإجازة.
     * @returns {string} - HTML لصف الإجازة.
     */
    function generateLeaveRow(lv) {
        const isPaidBadge = lv.is_paid == 1 ? '<span class="badge bg-success">نعم</span>' : '<span class="badge bg-danger">لا</span>';
        const typeBadge = lv.is_companion == 1 ? '<span class="badge bg-warning text-dark">مرافق</span>' : '<span class="badge bg-info text-dark">أساسي</span>';
        const paymentBtnClass = lv.is_paid == 1 ? 'd-none' : ''; // إخفاء زر الدفع إذا كانت مدفوعة

        return `
            <tr data-id="${lv.id}" data-patient="${lv.patient_id}" data-doctor="${lv.doctor_id}"
                data-comp-name="${htmlspecialchars(lv.companion_name || '')}"
                data-comp-rel="${htmlspecialchars(lv.companion_relation || '')}"
                data-is-paid="${lv.is_paid}">
                <td class="row-num"></td>
                <td class="cell-service">${htmlspecialchars(lv.service_code.toUpperCase())}</td>
                <td class="cell-patient">${htmlspecialchars(lv.patient_name)}</td>
                <td class="cell-identity">${htmlspecialchars(lv.identity_number)}</td>
                <td class="cell-doctor">${htmlspecialchars(lv.doctor_name)}</td>
                <td>${htmlspecialchars(lv.doctor_title)}</td>
                <td>${htmlspecialchars(lv.doctor_note || '')}</td>
                <td class="cell-issue">${htmlspecialchars(lv.issue_date)}</td>
                <td>${htmlspecialchars(lv.start_date)}</td>
                <td>${htmlspecialchars(lv.end_date)}</td>
                <td>${htmlspecialchars(lv.days_count)}</td>
                <td>${typeBadge}</td>
                <td class="cell-queries-count">${lv.queries_count}</td>
                <td class="cell-created">${htmlspecialchars(lv.created_at)}</td>
                <td class="cell-is-paid">${isPaidBadge}</td>
                <td class="cell-amount">${parseFloat(lv.payment_amount).toFixed(2)}</td>
                <td>
                    <button class="btn btn-info btn-sm action-btn btn-edit-leave"><i class="bi bi-pencil-square"></i> تعديل</button>
                    <button class="btn btn-danger btn-sm action-btn btn-delete-leave"><i class="bi bi-archive-fill"></i> أرشفة</button>
                    <button class="btn btn-warning btn-sm action-btn btn-view-queries" data-leave-id="${lv.id}"><i class="bi bi-journal-text"></i> استعلامات</button>
                    <button class="btn btn-success btn-sm action-btn btn-mark-paid ${paymentBtnClass}" data-leave-id="${lv.id}" data-amount="${parseFloat(lv.payment_amount).toFixed(2)}"><i class="bi bi-cash-stack"></i> دفع</button>
                </td>
            </tr>
        `;
    }

    /**
     * تولّد صف HTML لبيانات الإجازة المؤرشفة.
     * @param {object} lv - بيانات الإجازة المؤرشفة.
     * @returns {string} - HTML لصف الإجازة المؤرشفة.
     */
    function generateArchivedLeaveRow(lv) {
        const isPaidBadge = lv.is_paid == 1 ? '<span class="badge bg-success">نعم</span>' : '<span class="badge bg-danger">لا</span>';
        const typeBadge = lv.is_companion == 1 ? '<span class="badge bg-warning text-dark">مرافق</span>' : '<span class="badge bg-info text-dark">أساسي</span>';
        return `
            <tr data-id="${lv.id}" data-patient="${lv.patient_id}" data-doctor="${lv.doctor_id}"
                data-comp-name="${htmlspecialchars(lv.companion_name || '')}"
                data-comp-rel="${htmlspecialchars(lv.companion_relation || '')}">
                <td class="row-num"></td>
                <td class="cell-service">${htmlspecialchars(lv.service_code.toUpperCase())}</td>
                <td class="cell-patient">${htmlspecialchars(lv.patient_name)}</td>
                <td class="cell-identity">${htmlspecialchars(lv.identity_number)}</td>
                <td class="cell-doctor">${htmlspecialchars(lv.doctor_name)}</td>
                <td>${htmlspecialchars(lv.doctor_title)}</td>
                <td>${htmlspecialchars(lv.doctor_note || '')}</td>
                <td class="cell-issue">${htmlspecialchars(lv.issue_date)}</td>
                <td>${htmlspecialchars(lv.start_date)}</td>
                <td>${htmlspecialchars(lv.end_date)}</td>
                <td>${htmlspecialchars(lv.days_count)}</td>
                <td>${typeBadge}</td>
                <td class="cell-queries-count">${lv.queries_count}</td>
                <td class="cell-deleted">${htmlspecialchars(lv.deleted_at)}</td>
                <td>${isPaidBadge}</td>
                <td>${parseFloat(lv.payment_amount).toFixed(2)}</td>
                <td>
                    <button class="btn btn-success btn-sm action-btn btn-restore-leave"><i class="bi bi-arrow-counterclockwise"></i> استعادة</button>
                    <button class="btn btn-danger btn-sm action-btn btn-force-delete-leave"><i class="bi bi-x-circle"></i> حذف نهائي</button>
                    <button class="btn btn-warning btn-sm action-btn btn-view-queries" data-leave-id="${lv.id}"><i class="bi bi-journal-text"></i> استعلامات</button>
                </td>
            </tr>
        `;
    }

    /**
     * تولّد صف HTML لبيانات الاستعلام.
     * @param {object} q - بيانات الاستعلام.
     * @returns {string} - HTML لصف الاستعلام.
     */
    function generateQueryRow(q) {
        return `
            <tr data-id="${q.qid}" data-leave-id="${q.leave_id}">
                <td class="row-num"></td>
                <td class="cell-service">${htmlspecialchars(q.service_code.toUpperCase())}</td>
                <td class="cell-patient">${htmlspecialchars(q.patient_name)}</td>
                <td class="cell-identity">${htmlspecialchars(q.identity_number)}</td>
                <td class="cell-queried">${htmlspecialchars(q.queried_at)}</td>
                <td>
                    <button class="btn btn-danger btn-sm action-btn btn-delete-query"><i class="bi bi-trash-fill"></i> حذف</button>
                    <button class="btn btn-info btn-sm action-btn btn-view-leave-from-query" data-leave-id="${q.leave_id}"><i class="bi bi-info-circle"></i> تفاصيل إجازة</button>
                </td>
            </tr>
        `;
    }

    /**
     * يقوم بتحديث قائمة إشعارات المدفوعات.
     * @param {Array<object>} notifications - مصفوفة الإشعارات.
     */
    function updatePaymentNotifications(notifications) {
        notifPaymentsList.innerHTML = '';
        if (notifications.length === 0) {
            notifPaymentsList.innerHTML = '<li class="list-group-item">لا توجد إشعارات مدفوعات حاليًا.</li>';
            return;
        }
        notifications.forEach(n => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.setAttribute('data-leave', n.leave_id);
            li.setAttribute('data-id', n.id);
            li.setAttribute('data-amount', parseFloat(n.payment_amount || 0).toFixed(2));
            li.innerHTML = `
                <span>${htmlspecialchars(n.message)}</span>
                <div class="btn-group">
                    <button class="btn btn-info btn-sm btn-view-leave" data-leave="${n.leave_id}"><i class="bi bi-info-circle"></i> تفاصيل</button>
                    <button class="btn btn-success btn-sm btn-pay-notif" data-leave="${n.leave_id}"><i class="bi bi-cash-stack"></i> مدفوعة</button>
                    <button class="btn btn-danger btn-sm btn-del-notif" data-id="${n.id}"><i class="bi bi-trash-fill"></i> حذف</button>
                </div>
            `;
            notifPaymentsList.appendChild(li);
        });
    }

    // ======================== وظائف الفرز، البحث، والفلترة ========================

    /**
     * يقوم بفلترة وترتيب البيانات في الجدول.
     * @param {HTMLTableElement} tableElement - الجدول المراد فلترته/فرزه.
     * @param {Array<object>} originalData - البيانات الأصلية للجدول.
     * @param {function(object): string} rowGenerator - دالة تولّد HTML لصف واحد.
     * @param {object} filters - كائن يحتوي على معايير الفلترة (search, fromDate, toDate, typeFilter).
     * @param {string} sortColumn - العمود الذي سيتم الفرز على أساسه.
     * @param {string} sortOrder - ترتيب الفرز ('asc' أو 'desc').
     */
    function filterAndSortTable(tableElement, originalData, rowGenerator, filters, sortColumn = null, sortOrder = 'desc') {
        let filteredData = [...originalData];

        // 1. الفلترة
        if (filters.search) {
            const searchTerm = filters.search.toLowerCase();
            filteredData = filteredData.filter(item => {
                if (tableElement.id === 'leavesTable' || tableElement.id === 'archivedTable') {
                    return (item.service_code && item.service_code.toLowerCase().includes(searchTerm)) ||
                        (item.patient_name && item.patient_name.toLowerCase().includes(searchTerm)) ||
                        (item.doctor_name && item.doctor_name.toLowerCase().includes(searchTerm)) ||
                        (item.doctor_note && item.doctor_note.toLowerCase().includes(searchTerm)) ||
                        (item.identity_number && item.identity_number.toLowerCase().includes(searchTerm)); // البحث بالهوية
                } else if (tableElement.id === 'doctorsTable') {
                    return (item.name && item.name.toLowerCase().includes(searchTerm)) ||
                        (item.title && item.title.toLowerCase().includes(searchTerm)) ||
                        (item.note && item.note.toLowerCase().includes(searchTerm));
                } else if (tableElement.id === 'patientsTable') {
                    return (item.name && item.name.toLowerCase().includes(searchTerm)) ||
                        (item.identity_number && item.identity_number.toLowerCase().includes(searchTerm)) ||
                        (item.phone && item.phone.toLowerCase().includes(searchTerm));
                } else if (tableElement.id === 'queriesTable') {
                    return (item.service_code && item.service_code.toLowerCase().includes(searchTerm)) ||
                        (item.patient_name && item.patient_name.toLowerCase().includes(searchTerm)) ||
                        (item.identity_number && item.identity_number.toLowerCase().includes(searchTerm));
                } else if (tableElement.id === 'paymentsTable') {
                    return (item.name && item.name.toLowerCase().includes(searchTerm));
                }
                return true;
            });
        }

        if (filters.fromDate && filters.toDate) {
            const from = new Date(filters.fromDate + 'T00:00:00');
            const to = new Date(filters.toDate + 'T23:59:59'); // فلترة حتى نهاية اليوم
            filteredData = filteredData.filter(item => {
                let dateToCheck;
                if (tableElement.id === 'leavesTable') {
                    dateToCheck = new Date(item.created_at);
                } else if (tableElement.id === 'archivedTable') {
                    dateToCheck = new Date(item.deleted_at);
                } else if (tableElement.id === 'queriesTable') {
                    dateToCheck = new Date(item.queried_at);
                } else {
                    return true; // لا يوجد فلترة تاريخ لهذا الجدول
                }
                return dateToCheck >= from && dateToCheck <= to;
            });
        }

        if (filters.typeFilter === 'paid') {
            filteredData = filteredData.filter(item => item.is_paid == 1);
        } else if (filters.typeFilter === 'unpaid') {
            filteredData = filteredData.filter(item => item.is_paid == 0);
        }

        // 2. الفرز
        if (sortColumn) {
            filteredData.sort((a, b) => {
                let valA, valB;
                if (sortColumn.includes('date') || sortColumn.includes('created_at') || sortColumn.includes('deleted_at') || sortColumn.includes('queried_at')) {
                    valA = new Date(a[sortColumn] || '1970-01-01');
                    valB = new Date(b[sortColumn] || '1970-01-01');
                } else if (sortColumn.includes('amount') || sortColumn.includes('count')) {
                    valA = parseFloat(a[sortColumn] || 0);
                    valB = parseFloat(b[sortColumn] || 0);
                } else {
                    valA = String(a[sortColumn] || '').toLowerCase();
                    valB = String(b[sortColumn] || '').toLowerCase();
                }

                if (valA < valB) return sortOrder === 'asc' ? -1 : 1;
                if (valA > valB) return sortOrder === 'asc' ? 1 : -1;
                return 0;
            });
        }

        // 3. تحديث الجدول
        updateTable(tableElement, filteredData, rowGenerator);
    }

    // ======================== وظائف التصدير والطباعة ========================

    /**
     * تصدير محتوى الجدول إلى PDF.
     * @param {HTMLTableElement} tableElement - عنصر الجدول المراد تصديره.
     * @param {string} filename - اسم ملف PDF.
     * @param {string} title - عنوان يظهر في ملف PDF.
     */
    function exportTableToPdf(tableElement, filename = 'document.pdf', title = 'تقرير') {
        const {
            jsPDF
        } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4'); // 'l' for landscape

        // Set font for Arabic support (Requires font to be added to jsPDF, e.g., using jspdf-arabic)
        // For simplicity, this example assumes you have 'Cairo-Regular.ttf' loaded or you're using a version of jsPDF
        // that handles complex scripts. In a real-world scenario, you might need a dedicated library.
        // If not using a specialized plugin, Arabic text might appear disconnected.
        if (typeof doc.addFont === 'function') { // Check if addFont is available (e.g., with jspdf-arabic)
            doc.addFont('Cairo-Regular.ttf', 'Cairo', 'normal');
            doc.setFont('Cairo');
        } else {
            console.warn("jsPDF's addFont not found. Arabic text might not render correctly. Consider jspdf-arabic plugin.");
        }


        const tableColumn = [];
        // Extract headers, excluding the last control column
        tableElement.querySelectorAll('thead th:not(:last-child)').forEach(th => {
            tableColumn.push(th.textContent.trim().split(' ')[0]); // Remove sort icons
        });

        const tableRows = [];
        tableElement.querySelectorAll('tbody tr:not(.no-results)').forEach(tr => {
            const rowData = [];
            // Extract cell data, excluding the last control column
            tr.querySelectorAll('td:not(:last-child)').forEach(td => {
                // If it's a badge, get its text content
                if (td.querySelector('.badge')) {
                    rowData.push(td.querySelector('.badge').textContent.trim());
                } else {
                    rowData.push(td.textContent.trim());
                }
            });
            tableRows.push(rowData);
        });

        doc.autoTable({
            head: [tableColumn],
            body: tableRows,
            startY: 20,
            theme: 'striped',
            headStyles: {
                fillColor: [32, 162, 178], // Adjust color to primary-color equivalent if needed
                textColor: [255, 255, 255],
                font: 'Cairo', // Use the Arabic font
                fontStyle: 'normal',
                halign: 'center', // Align header text to center
                fontEncoding: 'UTF-8' // Explicitly set UTF-8
            },
            bodyStyles: {
                textColor: [0, 0, 0],
                font: 'Cairo', // Use the Arabic font
                fontStyle: 'normal',
                halign: 'center', // Align body text to center
                fontEncoding: 'UTF-8' // Explicitly set UTF-8
            },
            alternateRowStyles: {
                fillColor: [240, 240, 240]
            },
            styles: {
                font: 'Cairo',
                fontStyle: 'normal',
                fontSize: 8,
                cellPadding: 2,
                overflow: 'linebreak',
                minCellHeight: 8,
                cellWidth: 'wrap'
            },
            margin: {
                top: 10,
                right: 10,
                bottom: 10,
                left: 10
            },
            didDrawPage: function (data) {
                // Header
                doc.setFontSize(14);
                doc.setTextColor(40);
                doc.text(title, doc.internal.pageSize.width / 2, 10, {
                    align: 'center'
                });

                // Footer
                doc.setFontSize(8);
                const pageCount = doc.internal.getNumberOfPages();
                for (let i = 0; i < pageCount; i++) {
                    doc.setPage(i);
                    doc.text('صفحة ' + (i + 1) + ' من ' + pageCount, doc.internal.pageSize.width - 20, doc.internal.pageSize.height - 10, {
                        align: 'right'
                    });
                }
            }
        });

        doc.save(filename);
        showToast('تم تصدير الجدول إلى PDF.', 'success');
    }

    /**
     * تصدير محتوى الجدول إلى ملف Excel (CSV).
     * @param {HTMLTableElement} tableElement - عنصر الجدول المراد تصديره.
     * @param {string} filename - اسم ملف Excel (مع امتداد .csv).
     */
    function exportTableToExcel(tableElement, filename = 'data.csv') {
        let csv = [];
        const rows = tableElement.querySelectorAll('tr');

        rows.forEach(row => {
            // Get all cells except the last one (control buttons)
            const cols = Array.from(row.querySelectorAll('th:not(:last-child), td:not(:last-child)'));
            const rowData = [];
            cols.forEach(col => {
                let text = col.textContent.trim();
                // Replace commas with semicolons or remove them to avoid issues in CSV
                text = text.replace(/,/g, ''); // Remove commas
                // Handle badges specially
                if (col.querySelector('.badge')) {
                    text = col.querySelector('.badge').textContent.trim();
                }
                rowData.push(`"${text}"`); // Enclose in quotes to handle spaces/special characters
            });
            csv.push(rowData.join(','));
        });

        const csvString = csv.join('\n');
        const blob = new Blob(["\uFEFF", csvString], { // Add BOM for UTF-8 in Excel
            type: 'text/csv;charset=utf-8;'
        });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showToast('تم تصدير الجدول إلى Excel (CSV).', 'success');
    }


    /**
     * طباعة محتوى الجدول.
     * @param {HTMLTableElement} tableElement - عنصر الجدول المراد طباعته.
     * @param {string} title - عنوان يظهر في صفحة الطباعة.
     */
    function printTableContent(tableElement, title = 'تقرير') {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>' + title + '</title>');
        printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">');
        printWindow.document.write('<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">'); // Include Cairo font
        printWindow.document.write('<style>');
        printWindow.document.write(`
            body { font-family: 'Cairo', sans-serif; direction: rtl; text-align: center; margin: 20px; }
            h1 { font-size: 24px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: center; } /* Changed to center for consistency */
            th { background-color: #f2f2f2; }
            .badge { padding: 0.3em 0.6em; font-size: 0.75em; font-weight: bold; border-radius: 0.25rem; }
            .badge.bg-warning { background-color: #ffc107; color: #212529; }
            .badge.bg-info { background-color: #17a2b8; color: #fff; }
            .badge.bg-success { background-color: #28a745; color: #fff; }
            .badge.bg-danger { background-color: #dc3545; color: #fff; }
            /* Hide the last column (control buttons) for printing */
            table th:last-child, table td:last-child { display: none; }
        `);
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<h1>' + title + '</h1>');
        printWindow.document.write(tableElement.outerHTML); // Use outerHTML to include the table tags
        printWindow.document.close();
        printWindow.print();
    }


    // ======================== وظائف الوضع الداكن/الفاتح ========================

    const darkModeToggle = document.getElementById('darkModeToggle');

    /**
     * يطبق الوضع الداكن أو الفاتح بناءً على تفضيل المستخدم.
     */
    function applyDarkModePreference() {
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '<i class="bi bi-sun-fill"></i> فاتح';
        } else {
            document.body.classList.remove('dark-mode');
            darkModeToggle.innerHTML = '<i class="bi bi-moon-fill"></i> داكن';
        }
    }
    // تحت المتغيرات العامة داخل DOMContentLoaded


    function renderDetailQueries(arr) {
        if (!arr.length) {
            queriesDetailsContainer.innerHTML = '<p class="text-center">لا توجد سجلات استعلام لهذه الإجازة.</p>';
            return;
        }
        queriesDetailsContainer.innerHTML = '<ul class="list-group" id="detailedQueriesList"></ul>';
        const list = document.getElementById('detailedQueriesList');
        arr.forEach(q => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.setAttribute('data-id', q.id || q.qid);
            li.innerHTML = `
      <span>${htmlspecialchars(q.queried_at)}</span>
      <button class="btn btn-danger btn-sm btn-delete-detail-query" data-id="${q.id || q.qid}">
        <i class="bi bi-trash-fill"></i> حذف
      </button>
    `;
            list.appendChild(li);
        });
    }

    document.getElementById('sortQueriesDetailNewest').addEventListener('click', () => {
        const sorted = [...currentDetailQueries].sort(
            (a, b) => new Date(b.queried_at) - new Date(a.queried_at)
        );
        renderDetailQueries(sorted);
    });

    document.getElementById('sortQueriesDetailOldest').addEventListener('click', () => {
        const sorted = [...currentDetailQueries].sort(
            (a, b) => new Date(a.queried_at) - new Date(b.queried_at)
        );
        renderDetailQueries(sorted);
    });

    document.getElementById('sortQueriesDetailReset').addEventListener('click', () => {
        renderDetailQueries(currentDetailQueries);
    });



    // ======================== تهيئة الأحداث (Event Listeners) ========================

    // تطبيق الوضع الداكن عند تحميل الصفحة
    applyDarkModePreference();

    // التبديل بين الوضع الداكن والفاتح
    darkModeToggle.addEventListener('click', () => {
        const isDarkMode = document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', isDarkMode);
        applyDarkModePreference(); // تحديث النص والأيقونة
    });

    // ====== أحداث نموذج إضافة إجازة ======

    // حساب عدد الأيام تلقائياً أو تمكين الإدخال اليدوي
    [issueDateInput, startDateInput, endDateInput].forEach(input => {
        input.addEventListener('change', () => {
            if (!daysManualCheckbox.checked) {
                daysCountInput.value = calculateDays(startDateInput.value, endDateInput.value);
            }
        });
    });

    daysManualCheckbox.addEventListener('change', () => {
        daysCountInput.readOnly = !daysManualCheckbox.checked;
        if (!daysManualCheckbox.checked) {
            daysCountInput.value = calculateDays(startDateInput.value, endDateInput.value);
        }
        daysCountInput.reportValidity(); // تحديث حالة التحقق
    });

    // إظهار/إخفاء حقول المرافق
    companionCheckbox.addEventListener('change', () => {
        companionFields.forEach(field => {
            const inputElement = field.querySelector('input, textarea');
            field.classList.toggle('hidden-field', !companionCheckbox.checked);
            if (!companionCheckbox.checked) {
                if (inputElement) inputElement.value = ''; // مسح القيم عند الإخفاء
                if (inputElement) inputElement.removeAttribute('required'); // إزالة required
            } else {
                if (inputElement) inputElement.setAttribute('required', 'required'); // إضافة required
            }
            if (inputElement) inputElement.reportValidity(); // تحديث حالة التحقق
        });
    });

    // إظهار/إخفاء حقول المريض اليدوية
    patientSelect.addEventListener('change', () => {
        togglePatientManualFields();
    });

    function togglePatientManualFields() {
        const isManual = patientSelect.value === 'manual';
        patientManualName.classList.toggle('hidden-field', !isManual);
        patientManualId.classList.toggle('hidden-field', !isManual);
        patientManualPhone.classList.toggle('hidden-field', !isManual);

        patientManualName.required = isManual;
        patientManualId.required = isManual;

        searchPatientInput.classList.toggle('hidden-field', isManual);
        document.getElementById('btn-search-patient').classList.toggle('hidden-field', isManual);

        // مسح الحقول اليدوية إذا لم يتم اختيار "إدخال يدوي"
        if (!isManual) {
            patientManualName.value = '';
            patientManualId.value = '';
            patientManualPhone.value = '';
            patientManualName.classList.remove('is-invalid');
            patientManualId.classList.remove('is-invalid');
            noPatientResult.style.display = 'none'; // إخفاء رسالة "لم يتم العثور"
            searchPatientInput.value = ''; // مسح حقل البحث
        }
        // تحديث صلاحية الحقول بعد التغيير
        patientManualName.reportValidity();
        patientManualId.reportValidity();
    }

    // البحث في قائمة المرضى
    searchPatientInput.addEventListener('input', () => {
        const searchTerm = searchPatientInput.value.toLowerCase();
        let found = false;
        patientSelect.querySelectorAll('option:not([value="manual"]):not([value=""])').forEach(option => {
            const patientName = option.dataset.name;
            const patientIdentity = option.dataset.identity;
            const patientPhone = option.dataset.phone || '';
            const matches = patientName.includes(searchTerm) || patientIdentity.includes(searchTerm) || patientPhone.includes(searchTerm);
            option.style.display = matches ? '' : 'none';
            if (matches) found = true;
        });
        noPatientResult.style.display = found || !searchTerm ? 'none' : 'block';
    });
    document.getElementById('btn-search-patient').addEventListener('click', () => {
        searchPatientInput.dispatchEvent(new Event('input')); // لتشغيل البحث يدوياً
    });

    // إظهار/إخفاء حقول الطبيب اليدوية
    doctorSelect.addEventListener('change', () => {
        toggleDoctorManualFields();
    });

    function toggleDoctorManualFields() {
        const isManual = doctorSelect.value === 'manual';
        const isSelectedDoctor = doctorSelect.value && doctorSelect.value !== 'manual';

        doctorManualName.classList.toggle('hidden-field', !isManual);
        doctorManualTitle.classList.toggle('hidden-field', !isManual);
        doctorManualNote.classList.toggle('hidden-field', !isManual);
        doctorSavedTitle.classList.toggle('hidden-field', isManual || !isSelectedDoctor);
        doctorSavedNote.classList.toggle('hidden-field', isManual || !isSelectedDoctor);

        doctorManualName.required = isManual;
        doctorManualTitle.required = isManual;

        searchDoctorInput.classList.toggle('hidden-field', isManual);
        document.getElementById('btn-search-doctor').classList.toggle('hidden-field', isManual);

        if (isSelectedDoctor) {
            const selectedOption = doctorSelect.options[doctorSelect.selectedIndex];
            doctorSavedTitle.value = selectedOption.dataset.title || '';
            doctorSavedNote.value = selectedOption.dataset.note || '';
        } else {
            doctorSavedTitle.value = '';
            doctorSavedNote.value = '';
        }

        // مسح الحقول اليدوية إذا لم يتم اختيار "إدخال يدوي"
        if (!isManual) {
            doctorManualName.value = '';
            doctorManualTitle.value = '';
            doctorManualNote.value = '';
            doctorManualName.classList.remove('is-invalid');
            doctorManualTitle.classList.remove('is-invalid');
            noDoctorResult.style.display = 'none'; // إخفاء رسالة "لم يتم العثور"
            searchDoctorInput.value = ''; // مسح حقل البحث
        }
        // تحديث صلاحية الحقول بعد التغيير
        doctorManualName.reportValidity();
        doctorManualTitle.reportValidity();
    }
    // تحديث الحالة الأولية عند تحميل الصفحة
    togglePatientManualFields();
    toggleDoctorManualFields();


    // البحث في قائمة الأطباء
    searchDoctorInput.addEventListener('input', () => {
        const searchTerm = searchDoctorInput.value.toLowerCase();
        let found = false;
        doctorSelect.querySelectorAll('option:not([value="manual"]):not([value=""])').forEach(option => {
            const doctorName = option.dataset.name;
            const doctorTitle = option.dataset.title;
            const doctorNote = option.dataset.note;
            const matches = doctorName.includes(searchTerm) || doctorTitle.includes(searchTerm) || doctorNote.includes(searchTerm);
            option.style.display = matches ? '' : 'none';
            if (matches) found = true;
        });
        noDoctorResult.style.display = found || !searchTerm ? 'none' : 'block';
    });
    document.getElementById('btn-search-doctor').addEventListener('click', () => {
        searchDoctorInput.dispatchEvent(new Event('input')); // لتشغيل البحث يدوياً
    });

    // ====== معالجة إضافة إجازة ======
    leaveForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm(leaveForm)) {
            showToast('الرجاء تعبئة جميع الحقول المطلوبة بشكل صحيح.', 'danger');
            return;
        }

        const formData = new FormData(leaveForm);
        const result = await sendAjaxRequest('add_leave', formData);

        if (result.success) {
            showToast(result.message, 'success');
            clearForm(leaveForm); // مسح النموذج بعد الإضافة
            // تحديث الجدول والإحصائيات
            // لا نستخدم unshift هنا بل نعتمد على fetchAllLeaves لضمان جلب كافة البيانات المحدثة
            await fetchAllLeaves();
        }
    });

    // ====== أحداث إدارة الأطباء (داخل مودال الأطباء) ======
    const doctorForm = document.getElementById('doctorForm');
    const doctorFormId = document.getElementById('doctor_form_id');
    const doctorFormName = document.getElementById('doctor_form_name');
    const doctorFormTitle = document.getElementById('doctor_form_title');
    const doctorFormNote = document.getElementById('doctor_form_note');

    document.getElementById('btn-show-add-doctor').addEventListener('click', () => {
        clearForm(doctorForm);
        doctorForm.style.display = 'flex'; // إظهار النموذج
    });

    document.getElementById('btn-cancel-doctor').addEventListener('click', () => {
        clearForm(doctorForm);
        doctorForm.style.display = 'none'; // إخفاء النموذج
    });

    doctorForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm(doctorForm)) {
            showToast('الرجاء تعبئة جميع الحقول المطلوبة.', 'danger');
            return;
        }

        const action = doctorFormId.value ? 'edit_doctor' : 'add_doctor';
        const formData = new FormData(doctorForm);
        const result = await sendAjaxRequest(action, formData);

        if (result.success) {
            showToast(result.message || 'تم تحديث الأطباء بنجاح.', 'success');
            clearForm(doctorForm);
            doctorForm.style.display = 'none'; // إخفاء النموذج
            await fetchDoctors(result.doctor ? result.doctor.id : null); // إعادة جلب وتحديث الأطباء وتحديد الطبيب المضاف/المعدّل
            updateStats(result.stats);
        }
    });

    // تعديل طبيب
    doctorsTable.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-edit-doctor')) {
            const row = e.target.closest('tr');
            const doctorId = row.dataset.id;
            const doctorData = currentTableData.doctors.find(d => d.id == doctorId); // جلب البيانات من الكاش

            if (doctorData) {
                doctorFormId.value = doctorData.id;
                doctorFormName.value = doctorData.name;
                doctorFormTitle.value = doctorData.title;
                doctorFormNote.value = doctorData.note || '';
                doctorForm.style.display = 'flex'; // إظهار النموذج للتعديل
            } else {
                showToast('لم يتم العثور على بيانات الطبيب للتعديل.', 'danger');
            }
        }
    });

    // حذف طبيب
    doctorsTable.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-delete-doctor')) {
            const row = e.target.closest('tr');
            const doctorId = row.dataset.id;
            confirmMessage.textContent = 'هل أنت متأكد من حذف هذا الطبيب؟ سيتم منع إضافة إجازات جديدة بهذا الطبيب، ولكن الإجازات المرتبطة به ستظل موجودة (باسم الطبيب القديم).';
            confirmYesBtn.textContent = 'نعم، احذف الطبيب'; // تخصيص نص الزر
            currentConfirmAction = async () => {
                const result = await sendAjaxRequest('delete_doctor', {
                    doctor_id: doctorId
                });
                if (result.success) {
                    showToast(result.message || 'تم حذف الطبيب بنجاح.', 'success');
                    await fetchDoctors(); // إعادة جلب الأطباء بعد الحذف
                    updateStats(result.stats);
                }
            };
            confirmModal.show();
        }
    });

    // البحث في جدول الأطباء
    document.getElementById('searchDoctorsTable').addEventListener('input', () => {
        filterAndSortTable(doctorsTable, currentTableData.doctors, generateDoctorRow, {
            search: document.getElementById('searchDoctorsTable').value
        });
    });
    document.getElementById('btn-search-doctors').addEventListener('click', () => {
        document.getElementById('searchDoctorsTable').dispatchEvent(new Event('input'));
    });

    // ====== أحداث إدارة المرضى (داخل مودال المرضى) ======
    const patientForm = document.getElementById('patientForm');
    const patientFormId = document.getElementById('patient_form_id');
    const patientFormName = document.getElementById('patient_form_name');
    const patientFormIdentity = document.getElementById('patient_form_identity');
    const patientFormPhone = document.getElementById('patient_form_phone');

    document.getElementById('btn-show-add-patient').addEventListener('click', () => {
        clearForm(patientForm);
        patientForm.style.display = 'flex'; // إظهار النموذج
    });

    document.getElementById('btn-cancel-patient').addEventListener('click', () => {
        clearForm(patientForm);
        patientForm.style.display = 'none'; // إخفاء النموذج
    });

    patientForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm(patientForm)) {
            showToast('الرجاء تعبئة جميع الحقول المطلوبة.', 'danger');
            return;
        }

        const action = patientFormId.value ? 'edit_patient' : 'add_patient';
        const formData = new FormData(patientForm);
        const result = await sendAjaxRequest(action, formData);

        if (result.success) {
            showToast(result.message || 'تم تحديث المرضى بنجاح.', 'success');
            clearForm(patientForm);
            patientForm.style.display = 'none'; // إخفاء النموذج
            await fetchPatients(result.patient ? result.patient.id : null); // إعادة جلب وتحديث المرضى
            updateStats(result.stats);
        }
    });

    // تعديل مريض
    patientsTable.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-edit-patient')) {
            const row = e.target.closest('tr');
            const patientId = row.dataset.id;
            const patientData = currentTableData.patients.find(p => p.id == patientId); // جلب البيانات من الكاش

            if (patientData) {
                patientFormId.value = patientData.id;
                patientFormName.value = patientData.name;
                patientFormIdentity.value = patientData.identity_number;
                patientFormPhone.value = patientData.phone || '';
                patientForm.style.display = 'flex'; // إظهار النموذج للتعديل
            } else {
                showToast('لم يتم العثور على بيانات المريض للتعديل.', 'danger');
            }
        }
    });

    // حذف مريض
    patientsTable.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-delete-patient')) {
            const row = e.target.closest('tr');
            const patientId = row.dataset.id;
            confirmMessage.textContent = 'هل أنت متأكد من حذف هذا المريض؟ سيتم منع إضافة إجازات جديدة لهذا المريض، ولكن الإجازات المرتبطة به ستظل موجودة (باسم المريض القديم).';
            confirmYesBtn.textContent = 'نعم، احذف المريض'; // تخصيص نص الزر
            currentConfirmAction = async () => {
                const result = await sendAjaxRequest('delete_patient', {
                    patient_id: patientId
                });
                if (result.success) {
                    showToast(result.message || 'تم حذف المريض بنجاح.', 'success');
                    await fetchPatients(); // إعادة جلب المرضى بعد الحذف
                    updateStats(result.stats);
                }
            };
            confirmModal.show();
        }
    });

    // البحث في جدول المرضى
    document.getElementById('searchPatientsTable').addEventListener('input', () => {
        filterAndSortTable(patientsTable, currentTableData.patients, generatePatientRow, {
            search: document.getElementById('searchPatientsTable').value
        });
    });
    document.getElementById('btn-search-patients').addEventListener('click', () => {
        document.getElementById('searchPatientsTable').dispatchEvent(new Event('input'));
    });

    // ====== أحداث إدارة الإجازات (الجدول الرئيسي) ======

    // فتح مودال تأكيد الدفع للإجازة النشطة
    leavesTable.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-mark-paid');
        if (!btn) return;

        const leaveId = btn.dataset.leaveId;
        const defaultAmount = btn.dataset.amount;

        // إعداد المودال
        document.getElementById('payConfirmMessage').textContent = `هل تريد تأكيد دفع هذه الإجازة برمز ${leaveId}?`;
        document.getElementById('confirmPayAmount').value = defaultAmount;

        // تعيين الإجراء عند الضغط على "تأكيد الدفع"
        currentConfirmAction = async () => {
            const amount = document.getElementById('confirmPayAmount').value;
            const result = await sendAjaxRequest('mark_leave_paid', {
                leave_id: leaveId,
                amount: amount
            });
            if (result.success) {
                showToast(result.message, 'success');
                await fetchAllLeaves(); // إعادة تحميل الجداول
            }
        };

        payConfirmModal.show();
    });

    // معالجة تعديل إجازة (فتح المودال وملء البيانات)
    leavesTable.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-edit-leave')) {
            const row = e.target.closest('tr');
            const leaveId = row.dataset.id;
            const leaveData = currentTableData.leaves.find(l => l.id == leaveId);

            if (leaveData) {
                // مسح النموذج قبل ملء البيانات الجديدة
                clearForm(editLeaveForm);

                document.getElementById('leave_id_edit').value = leaveData.id;
                document.getElementById('service_code_edit').value = leaveData.service_code;
                document.getElementById('issue_date_edit').value = leaveData.issue_date;
                document.getElementById('patient_edit').value = leaveData.patient_name + ' (' + leaveData.identity_number + ')';
                document.getElementById('doctor_edit').value = leaveData.doctor_name + ' - ' + leaveData.doctor_title;
                document.getElementById('doctor_note_edit').value = leaveData.doctor_note || '';
                document.getElementById('start_date_edit').value = leaveData.start_date;
                document.getElementById('end_date_edit').value = leaveData.end_date;
                document.getElementById('days_count_edit').value = leaveData.days_count;

                // Companion fields
                const isCompanionEdit = document.getElementById('is_companion_edit');
                const companionNameEdit = document.getElementById('companion_name_edit');
                const companionRelationEdit = document.getElementById('companion_relation_edit');
                isCompanionEdit.checked = leaveData.is_companion == 1;
                document.querySelectorAll('.companion-fields-edit').forEach(field => {
                    field.classList.toggle('hidden-field', !isCompanionEdit.checked);
                });
                companionNameEdit.value = leaveData.companion_name || '';
                companionRelationEdit.value = leaveData.companion_relation || '';
                companionNameEdit.required = isCompanionEdit.checked;
                companionRelationEdit.required = isCompanionEdit.checked;

                // Payment fields
                const isPaidEdit = document.getElementById('is_paid_edit');
                const paymentAmountEdit = document.getElementById('payment_amount_edit');
                isPaidEdit.checked = leaveData.is_paid == 1;
                paymentAmountEdit.value = parseFloat(leaveData.payment_amount).toFixed(2);

                // Days manual checkbox for edit form
                const daysManualEditCheckbox = document.getElementById('days_manual_edit');
                daysManualEditCheckbox.checked = false; // افتراضياً غير يدوي
                document.getElementById('days_count_edit').readOnly = true;

                // Attach event listeners for edit form dates and days manual after populating
                // This ensures listeners are only on the modal's inputs
                const editStartDateInput = document.getElementById('start_date_edit');
                const editEndDateInput = document.getElementById('end_date_edit');
                const editDaysCountInput = document.getElementById('days_count_edit');

                const updateEditDays = () => {
                    if (!daysManualEditCheckbox.checked) {
                        editDaysCountInput.value = calculateDays(editStartDateInput.value, editEndDateInput.value);
                    }
                };
                editStartDateInput.onchange = updateEditDays;
                editEndDateInput.onchange = updateEditDays;
                daysManualEditCheckbox.onchange = () => {
                    editDaysCountInput.readOnly = !daysManualEditCheckbox.checked;
                    if (!daysManualEditCheckbox.checked) {
                        updateEditDays();
                    }
                    editDaysCountInput.reportValidity();
                };

                isCompanionEdit.onchange = () => {
                    document.querySelectorAll('.companion-fields-edit').forEach(field => {
                        const inputEl = field.querySelector('input, textarea');
                        field.classList.toggle('hidden-field', !isCompanionEdit.checked);
                        if (inputEl) inputEl.required = isCompanionEdit.checked;
                        if (inputEl) inputEl.reportValidity();
                    });
                };


                editLeaveModal.show();
            } else {
                showToast('لم يتم العثور على بيانات الإجازة للتعديل.', 'danger');
            }
        }
    });


    // إرسال نموذج تعديل إجازة
    editLeaveForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!validateForm(editLeaveForm)) {
            showToast('الرجاء تعبئة جميع الحقول المطلوبة بشكل صحيح.', 'danger');
            return;
        }

        const formData = new FormData(editLeaveForm);
        const result = await sendAjaxRequest('edit_leave', formData);

        if (result.success) {
            showToast(result.message, 'success');
            editLeaveModal.hide();
            await fetchAllLeaves(); // إعادة جلب وتحديث جميع الجداول بعد التعديل
        }
    });

    // أرشفة إجازة
    leavesTable.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-delete-leave')) {
            const row = e.target.closest('tr');
            const leaveId = row.dataset.id;
            confirmMessage.textContent = 'هل أنت متأكد من أرشفة هذه الإجازة؟ سيتم نقلها إلى الأرشيف.';
            confirmYesBtn.textContent = 'نعم، أرشف';
            currentConfirmAction = async () => {
                const result = await sendAjaxRequest('delete_leave', {
                    leave_id: leaveId
                });
                if (result.success) {
                    showToast(result.message, 'success');
                    await fetchAllLeaves(); // إعادة جلب وتحديث جميع الجداول
                }
            };
            confirmModal.show();
        }
    });

    // استعادة إجازة من الأرشيف
    // فتح مودال تفاصيل الاستعلامات لإجازة مؤرشفة
    archivedTable.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-view-queries')) {
            const leaveId = e.target.dataset.leaveId;
            queriesDetailsContainer.innerHTML = '<p class="text-center">جارٍ جلب البيانات...</p>';
            viewQueriesModal.show();
            const result = await sendAjaxRequest('fetch_queries', { leave_id: leaveId });
            if (result.success) {
                currentDetailQueries = result.queries;
                renderDetailQueries(currentDetailQueries);
            } else {
                queriesDetailsContainer.innerHTML =
                    `<p class="text-center text-danger">${result.message}</p>`;
            }
        }
    });


    // حذف نهائي لإجازة من الأرشيف
    archivedTable.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-force-delete-leave')) {
            const row = e.target.closest('tr');
            const leaveId = row.dataset.id;
            confirmMessage.textContent = 'تحذير! هل أنت متأكد من الحذف النهائي لهذه الإجازة؟ لا يمكن التراجع عن هذا الإجراء.';
            confirmYesBtn.textContent = 'نعم، حذف نهائي';
            currentConfirmAction = async () => {
                const result = await sendAjaxRequest('force_delete_leave', {
                    leave_id: leaveId
                });
                if (result.success) {
                    showToast(result.message, 'success');
                    await fetchAllLeaves(); // إعادة جلب وتحديث جميع الجداول
                }
            };
            confirmModal.show();
        }
    });

    // استعادة إجازة من الأرشيف
    archivedTable.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-restore-leave')) {
            const row = e.target.closest('tr');
            const leaveId = row.dataset.id;
            confirmMessage.textContent = 'هل أنت متأكد من استعادة هذه الإجازة؟';
            confirmYesBtn.textContent = 'نعم، استعادة';
            currentConfirmAction = async () => {
                const result = await sendAjaxRequest('restore_leave', { leave_id: leaveId });
                if (result.success) {
                    showToast(result.message, 'success');
                    await fetchAllLeaves(); // إعادة تحميل البيانات والجداول
                }
            };
            confirmModal.show();
        }
    });

    // حذف كل الإجازات المؤرشفة نهائيًا
    document.getElementById('btn-delete-all-archived').addEventListener('click', () => {
        confirmMessage.textContent = 'تحذير! هل أنت متأكد من حذف جميع الإجازات المؤرشفة نهائيًا؟ لا يمكن التراجع عن هذا الإجراء.';
        confirmYesBtn.textContent = 'نعم، حذف الكل نهائيًا';
        currentConfirmAction = async () => {
            const result = await sendAjaxRequest('force_delete_all_archived', {});
            if (result.success) {
                showToast(result.message, 'success');
                await fetchAllLeaves(); // إعادة جلب وتحديث جميع الجداول
            }
        };
        confirmModal.show();
    });

    // تسجيل استعلام (علامة استعلام) - هذا الزر غير موجود حالياً في HTML لكن تم تضمين المنطق
    // إذا كنت تريد إضافة هذا الزر: أضفه ضمن عمود التحكم في جدول الإجازات النشطة
    // <button class="btn btn-primary btn-sm action-btn btn-add-query" data-leave-id="${lv.id}"><i class="bi bi-plus-circle"></i> تسجيل استعلام</button>
    leavesTable.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-add-query')) {
            const row = e.target.closest('tr');
            const leaveId = row.dataset.id;
            const result = await sendAjaxRequest('add_query', {
                leave_id: leaveId
            });
            if (result.success) {
                showToast(result.message, 'success');
                // تحديث عدد الاستعلامات في الصف مباشرة
                const queriesCountCell = row.querySelector('.cell-queries-count');
                if (queriesCountCell) {
                    queriesCountCell.textContent = result.new_count;
                }
                await fetchAllLeaves(); // لتحديث جدول سجل الاستعلامات الرئيسي
            }
        }
    });

    // فتح مودال تفاصيل الاستعلامات لإجازة معينة
    // فتح مودال تفاصيل الاستعلامات لإجازة معينة (محدَّثة)
    leavesTable.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-view-queries')) {
            const leaveId = e.target.dataset.leaveId;
            queriesDetailsContainer.innerHTML = '<p class="text-center">جارٍ جلب البيانات...</p>';
            viewQueriesModal.show();

            const result = await sendAjaxRequest('fetch_queries', { leave_id: leaveId });
            if (result.success) {
                currentDetailQueries = result.queries;
                renderDetailQueries(currentDetailQueries);
            } else {
                queriesDetailsContainer.innerHTML =
                    `<p class="text-center text-danger">${result.message}</p>`;
            }
        }
    });

    // فرز استعلامات المودال
    document.getElementById('sortQueriesDetailNewest').addEventListener('click', () => {
        const sorted = [...currentDetailQueries].sort(
            (a, b) => new Date(b.queried_at) - new Date(a.queried_at)
        );
        renderDetailQueries(sorted);
    });
    document.getElementById('sortQueriesDetailOldest').addEventListener('click', () => {
        const sorted = [...currentDetailQueries].sort(
            (a, b) => new Date(a.queried_at) - new Date(b.queried_at)
        );
        renderDetailQueries(sorted);
    });
    document.getElementById('sortQueriesDetailReset').addEventListener('click', () => {
        renderDetailQueries(currentDetailQueries);
    });


    // حذف استعلام من نافذة تفاصيل الاستعلام
    queriesDetailsContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-delete-detail-query')) {
            const queryId = e.target.dataset.id;
            confirmMessage.textContent = 'هل أنت متأكد من حذف سجل الاستعلام هذا؟';
            confirmYesBtn.textContent = 'نعم، احذف السجل';
            currentConfirmAction = async () => {
                const result = await sendAjaxRequest('delete_query', {
                    query_id: queryId
                });
                if (result.success) {
                    showToast(result.message, 'success');
                    // إزالة الصف من قائمة التفاصيل
                    e.target.closest('li').remove();
                    // تحديث عدد الاستعلامات في جدول الإجازات الرئيسية
                    const leaveRow = leavesTable.querySelector(`tr[data-id="${currentConfirmId}"]`);
                    if (leaveRow) {
                        const queriesCountCell = leaveRow.querySelector('.cell-queries-count');
                        if (queriesCountCell) {
                            queriesCountCell.textContent = parseInt(queriesCountCell.textContent) - 1;
                        }
                    }
                    await fetchAllLeaves(); // لتحديث جدول سجل الاستعلامات الرئيسي
                }
            };
            confirmModal.show();
        }
    });

    // حذف كل الاستعلامات لإجازة معينة من نافذة تفاصيل الاستعلامات
    document.getElementById('btn-delete-all-queries').addEventListener('click', () => {
        const leaveId = currentConfirmId; // معرف الإجازة المحدد حالياً
        confirmMessage.textContent = 'هل أنت متأكد من حذف جميع سجلات الاستعلامات لهذه الإجازة؟';
        confirmYesBtn.textContent = 'نعم، احذف الكل';
        currentConfirmAction = async () => {
            const result = await sendAjaxRequest('delete_all_queries_for_leave', {
                leave_id: leaveId
            });
            if (result.success) {
                showToast(result.message || 'تم حذف جميع الاستعلامات لهذه الإجازة.', 'success');
                queriesDetailsContainer.innerHTML = '<p class="text-center">لا توجد سجلات استعلام لهذه الإجازة.</p>';
                // تحديث عدد الاستعلامات في جدول الإجازات الرئيسية
                const leaveRow = leavesTable.querySelector(`tr[data-id="${leaveId}"]`);
                if (leaveRow) {
                    const queriesCountCell = leaveRow.querySelector('.cell-queries-count');
                    if (queriesCountCell) {
                        queriesCountCell.textContent = 0;
                    }
                }
                await fetchAllLeaves(); // لتحديث جدول سجل الاستعلامات الرئيسي
            }
        };
        confirmModal.show();
    });


    // ====== أحداث إدارة إشعارات المدفوعات ======

    // تحديث إشعارات المدفوعات (يمكن أن يتم استدعاؤها دورياً أو عند فتح المودال)
    document.getElementById('btn-payment-notifs').addEventListener('click', async () => {
        showLoading();
        const result = await sendAjaxRequest('fetch_notifications', {});
        hideLoading();
        if (result.success) {
            updatePaymentNotifications(result.data);
            currentTableData.notifications_payment = result.data;
        } else {
            showToast(result.message || 'فشل في جلب الإشعارات.', 'danger');
        }
    });

    // زر تحديث الإشعارات داخل المودال
    document.getElementById('refreshNotifs').addEventListener('click', async () => {
        showLoading();
        const result = await sendAjaxRequest('fetch_notifications', {});
        hideLoading();
        if (result.success) {
            updatePaymentNotifications(result.data);
            currentTableData.notifications_payment = result.data;
        } else {
            showToast(result.message || 'فشل في تحديث الإشعارات.', 'danger');
        }
    });

    // التعامل مع أزرار الإشعارات (تفاصيل، مدفوعة، حذف)
    notifPaymentsList.addEventListener('click', async (e) => {
        const targetBtn = e.target.closest('.btn'); // التأكد من أن الزر هو الهدف
        if (!targetBtn) return;

        const listItem = targetBtn.closest('li');
        const leaveId = listItem.dataset.leave;
        const notificationId = listItem.dataset.id;
        const paymentAmount = listItem.dataset.amount;

        if (targetBtn.classList.contains('btn-view-leave')) {
            showLoading();
            const result = await sendAjaxRequest('fetch_leave_details', {
                leave_id: leaveId
            });
            hideLoading();
            if (result.success && result.leave) {
                const leave = result.leave;
                leaveDetailsContainer.innerHTML = `
                    <p><strong>رمز الخدمة:</strong> ${htmlspecialchars(leave.service_code)}</p>
                    <p><strong>المريض:</strong> ${htmlspecialchars(leave.patient_name)} (${htmlspecialchars(leave.identity_number)})</p>
                    <p><strong>الطبيب:</strong> ${htmlspecialchars(leave.doctor_name)} (${htmlspecialchars(leave.doctor_title)})</p>
                    <p><strong>تاريخ الإصدار:</strong> ${htmlspecialchars(leave.issue_date)}</p>
                    <p><strong>بداية الإجازة:</strong> ${htmlspecialchars(leave.start_date)}</p>
                    <p><strong>نهاية الإجازة:</strong> ${htmlspecialchars(leave.end_date)}</p>
                    <p><strong>عدد الأيام:</strong> ${htmlspecialchars(leave.days_count)}</p>
                    <p><strong>نوع الإجازة:</strong> ${leave.is_companion == 1 ? 'مرافق: ' + htmlspecialchars(leave.companion_name) + ' (' + htmlspecialchars(leave.companion_relation) + ')' : 'أساسي'}</p>
                    <p><strong>مدفوعة:</strong> ${leave.is_paid == 1 ? 'نعم' : 'لا'}</p>
                    <p><strong>المبلغ:</strong> ${parseFloat(leave.payment_amount).toFixed(2)}</p>
                    <p><strong>تاريخ الإضافة:</strong> ${htmlspecialchars(leave.created_at)}</p>
                    <p><strong>تاريخ التعديل:</strong> ${htmlspecialchars(leave.updated_at || 'غير متوفر')}</p>
                    <p><strong>عدد الاستعلامات:</strong> ${leave.queries_count}</p>
                `;
                leaveDetailsModal.show();
            } else {
                showToast(result.message || 'فشل في جلب تفاصيل الإجازة.', 'danger');
            }
        } else if (targetBtn.classList.contains('btn-pay-notif')) {
            const leaveId = listItem.dataset.leave;
            const defaultAmount = listItem.dataset.amount;

            // عرض مودال الدفع
            document.getElementById('payConfirmMessage').textContent =
                `هل تريد تأكيد دفع الإجازة برمز ${leaveId}?`;
            document.getElementById('confirmPayAmount').value = defaultAmount;

            // تخزين الإجراء ليُنَفَّذ عند الضغط على تأكيد في المودال
            currentConfirmAction = async () => {
                const amount = document.getElementById('confirmPayAmount').value;
                const result = await sendAjaxRequest('mark_leave_paid', {
                    leave_id: leaveId,
                    amount: amount
                });
                if (result.success) {
                    showToast(result.message, 'success');
                    await fetchAllLeaves();
                    const notifs = await sendAjaxRequest('fetch_notifications', {});
                    if (notifs.success) updatePaymentNotifications(notifs.data);
                }
            };

            payConfirmModal.show();
        } else if (targetBtn.classList.contains('btn-del-notif')) {
            confirmMessage.textContent = 'هل أنت متأكد من حذف هذا الإشعار؟';
            confirmYesBtn.textContent = 'نعم، حذف الإشعار';
            currentConfirmAction = async () => {
                const result = await sendAjaxRequest('delete_notification', {
                    notification_id: notificationId
                });
                if (result.success) {
                    showToast(result.message, 'success');
                    listItem.remove(); // إزالة الإشعار من القائمة
                    // تحديث إشعارات المدفوعات مرة أخرى
                    await sendAjaxRequest('fetch_notifications', {}).then(res => {
                        if (res.success) updatePaymentNotifications(res.data);
                    });
                }
            };
            confirmModal.show();
        }
    });

    // زر تأكيد في مودال التأكيد
    confirmYesBtn.addEventListener('click', async () => {
        if (currentConfirmAction) {
            await currentConfirmAction();
        }
        confirmModal.hide();
        currentConfirmAction = null; // إعادة تعيين
        currentConfirmId = null;
        confirmYesBtn.textContent = 'تأكيد'; // إعادة النص الافتراضي
    });

    // ====== أحداث الفرز، البحث، والفلترة للجدول الرئيسي (الإجازات النشطة) ======

    // البحث
    document.getElementById('searchLeaves').addEventListener('input', () => {
        filterAndSortTable(leavesTable, currentTableData.leaves, generateLeaveRow, {
            search: document.getElementById('searchLeaves').value
        }, currentLeavesSortColumn, currentLeavesSortOrder);
    });
    document.getElementById('btn-search-leaves').addEventListener('click', () => {
        document.getElementById('searchLeaves').dispatchEvent(new Event('input'));
    });

    // الفلترة حسب التاريخ
    document.getElementById('btn-filter-dates').addEventListener('click', () => {
        const fromDate = document.getElementById('filter_from_date').value;
        const toDate = document.getElementById('filter_to_date').value;
        if (!fromDate || !toDate) {
            showToast('الرجاء اختيار نطاق تاريخ كامل للفلترة.', 'warning');
            return;
        }
        filterAndSortTable(leavesTable, currentTableData.leaves, generateLeaveRow, {
            fromDate: fromDate,
            toDate: toDate,
            search: document.getElementById('searchLeaves').value // الحفاظ على البحث أيضاً
        }, currentLeavesSortColumn, currentLeavesSortOrder);
    });
    document.getElementById('btn-reset-dates').addEventListener('click', () => {
        document.getElementById('filter_from_date').value = '';
        document.getElementById('filter_to_date').value = '';
        filterAndSortTable(leavesTable, currentTableData.leaves, generateLeaveRow, {
            search: document.getElementById('searchLeaves').value
        }, currentLeavesSortColumn, currentLeavesSortOrder); // إعادة تعيين الفلترة مع الحفاظ على البحث
    });

    // الفلترة حسب حالة الدفع
    document.getElementById('showPaidLeaves').addEventListener('click', () => {
        filterAndSortTable(leavesTable, currentTableData.leaves, generateLeaveRow, {
            typeFilter: 'paid',
            search: document.getElementById('searchLeaves').value,
            fromDate: document.getElementById('filter_from_date').value,
            toDate: document.getElementById('filter_to_date').value
        }, currentLeavesSortColumn, currentLeavesSortOrder);
    });
    document.getElementById('showUnpaidLeaves').addEventListener('click', () => {
        filterAndSortTable(leavesTable, currentTableData.leaves, generateLeaveRow, {
            typeFilter: 'unpaid',
            search: document.getElementById('searchLeaves').value,
            fromDate: document.getElementById('filter_from_date').value,
            toDate: document.getElementById('filter_to_date').value
        }, currentLeavesSortColumn, currentLeavesSortOrder);
    });
    document.getElementById('showAllLeaves').addEventListener('click', () => {
        filterAndSortTable(leavesTable, currentTableData.leaves, generateLeaveRow, {
            search: document.getElementById('searchLeaves').value,
            fromDate: document.getElementById('filter_from_date').value,
            toDate: document.getElementById('filter_to_date').value
        }, currentLeavesSortColumn, currentLeavesSortOrder); // عرض الكل
    });


    // الفرز
    let currentLeavesSortColumn = 'created_at';
    let currentLeavesSortOrder = 'desc';

    document.getElementById('sortLeavesNewest').addEventListener('click', () => {
        currentLeavesSortColumn = 'created_at';
        currentLeavesSortOrder = 'desc';
        filterAndSortTable(leavesTable, currentTableData.leaves, generateLeaveRow, {
            search: document.getElementById('searchLeaves').value,
            fromDate: document.getElementById('filter_from_date').value,
            toDate: document.getElementById('filter_to_date').value
        }, currentLeavesSortColumn, currentLeavesSortOrder);
    });

    document.getElementById('sortLeavesOldest').addEventListener('click', () => {
        currentLeavesSortColumn = 'created_at';
        currentLeavesSortOrder = 'asc';
        filterAndSortTable(leavesTable, currentTableData.leaves, generateLeaveRow, {
            search: document.getElementById('searchLeaves').value,
            fromDate: document.getElementById('filter_from_date').value,
            toDate: document.getElementById('filter_to_date').value
        }, currentLeavesSortColumn, currentLeavesSortOrder);
    });

    document.getElementById('sortLeavesReset').addEventListener('click', () => {
        currentLeavesSortColumn = 'created_at'; // الافتراضي
        currentLeavesSortOrder = 'desc'; // الافتراضي
        document.getElementById('searchLeaves').value = '';
        document.getElementById('filter_from_date').value = '';
        document.getElementById('filter_to_date').value = '';
        filterAndSortTable(leavesTable, currentTableData.leaves, generateLeaveRow, {}, currentLeavesSortColumn, currentLeavesSortOrder);
    });

    // ====== أحداث الفرز، البحث، والفلترة لجدول الأرشيف ======

    // البحث
    document.getElementById('searchArchived').addEventListener('input', () => {
        filterAndSortTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow, {
            search: document.getElementById('searchArchived').value
        }, currentArchivedSortColumn, currentArchivedSortOrder);
    });
    document.getElementById('btn-search-archived').addEventListener('click', () => {
        document.getElementById('searchArchived').dispatchEvent(new Event('input'));
    });

    // الفلترة حسب التاريخ
    document.getElementById('btn-filter-arch-dates').addEventListener('click', () => {
        const fromDate = document.getElementById('filter_arch_from_date').value;
        const toDate = document.getElementById('filter_arch_to_date').value;
        if (!fromDate || !toDate) {
            showToast('الرجاء اختيار نطاق تاريخ كامل للفلترة.', 'warning');
            return;
        }
        filterAndSortTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow, {
            fromDate: fromDate,
            toDate: toDate,
            search: document.getElementById('searchArchived').value
        }, currentArchivedSortColumn, currentArchivedSortOrder);
    });
    document.getElementById('btn-reset-arch-dates').addEventListener('click', () => {
        document.getElementById('filter_arch_from_date').value = '';
        document.getElementById('filter_arch_to_date').value = '';
        filterAndSortTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow, {
            search: document.getElementById('searchArchived').value
        }, currentArchivedSortColumn, currentArchivedSortOrder);
    });

    // الفلترة حسب حالة الدفع
    document.getElementById('showPaidArchived').addEventListener('click', () => {
        filterAndSortTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow, {
            typeFilter: 'paid',
            search: document.getElementById('searchArchived').value,
            fromDate: document.getElementById('filter_arch_from_date').value,
            toDate: document.getElementById('filter_arch_to_date').value
        }, currentArchivedSortColumn, currentArchivedSortOrder);
    });
    document.getElementById('showUnpaidArchived').addEventListener('click', () => {
        filterAndSortTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow, {
            typeFilter: 'unpaid',
            search: document.getElementById('searchArchived').value,
            fromDate: document.getElementById('filter_arch_from_date').value,
            toDate: document.getElementById('filter_arch_to_date').value
        }, currentArchivedSortColumn, currentArchivedSortOrder);
    });
    document.getElementById('showAllArchived').addEventListener('click', () => {
        filterAndSortTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow, {
            search: document.getElementById('searchArchived').value,
            fromDate: document.getElementById('filter_arch_from_date').value,
            toDate: document.getElementById('filter_arch_to_date').value
        }, currentArchivedSortColumn, currentArchivedSortOrder);
    });

    // الفرز
    let currentArchivedSortColumn = 'deleted_at';
    let currentArchivedSortOrder = 'desc';

    document.getElementById('sortArchivedNewest').addEventListener('click', () => {
        currentArchivedSortColumn = 'deleted_at';
        currentArchivedSortOrder = 'desc';
        filterAndSortTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow, {
            search: document.getElementById('searchArchived').value,
            fromDate: document.getElementById('filter_arch_from_date').value,
            toDate: document.getElementById('filter_arch_to_date').value
        }, currentArchivedSortColumn, currentArchivedSortOrder);
    });

    document.getElementById('sortArchivedOldest').addEventListener('click', () => {
        currentArchivedSortColumn = 'deleted_at';
        currentArchivedSortOrder = 'asc';
        filterAndSortTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow, {
            search: document.getElementById('searchArchived').value,
            fromDate: document.getElementById('filter_arch_from_date').value,
            toDate: document.getElementById('filter_arch_to_date').value
        }, currentArchivedSortColumn, currentArchivedSortOrder);
    });

    document.getElementById('sortArchivedReset').addEventListener('click', () => {
        currentArchivedSortColumn = 'deleted_at'; // الافتراضي
        currentArchivedSortOrder = 'desc'; // الافتراضي
        document.getElementById('searchArchived').value = '';
        document.getElementById('filter_arch_from_date').value = '';
        document.getElementById('filter_arch_to_date').value = '';
        filterAndSortTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow, {}, currentArchivedSortColumn, currentArchivedSortOrder);
    });

    // ====== أحداث الفرز، البحث، والفلترة لجدول سجل الاستعلامات ======

    // البحث
    document.getElementById('searchQueries').addEventListener('input', () => {
        filterAndSortTable(queriesTable, currentTableData.queries, generateQueryRow, {
            search: document.getElementById('searchQueries').value
        }, currentQueriesSortColumn, currentQueriesSortOrder);
    });
    document.getElementById('btn-search-queries').addEventListener('click', () => {
        document.getElementById('searchQueries').dispatchEvent(new Event('input'));
    });

    // الفلترة حسب التاريخ
    document.getElementById('btn-filter-queries-dates').addEventListener('click', () => {
        const fromDate = document.getElementById('filter_q_from_date').value;
        const toDate = document.getElementById('filter_q_to_date').value;
        if (!fromDate || !toDate) {
            showToast('الرجاء اختيار نطاق تاريخ كامل للفلترة.', 'warning');
            return;
        }
        filterAndSortTable(queriesTable, currentTableData.queries, generateQueryRow, {
            fromDate: fromDate,
            toDate: toDate,
            search: document.getElementById('searchQueries').value
        }, currentQueriesSortColumn, currentQueriesSortOrder);
    });
    document.getElementById('btn-reset-queries-dates').addEventListener('click', () => {
        document.getElementById('filter_q_from_date').value = '';
        document.getElementById('filter_q_to_date').value = '';
        filterAndSortTable(queriesTable, currentTableData.queries, generateQueryRow, {
            search: document.getElementById('searchQueries').value
        }, currentQueriesSortColumn, currentQueriesSortOrder);
    });

    // الفرز
    let currentQueriesSortColumn = 'queried_at';
    let currentQueriesSortOrder = 'desc';

    document.getElementById('sortQueriesNewest').addEventListener('click', () => {
        currentQueriesSortColumn = 'queried_at';
        currentQueriesSortOrder = 'desc';
        filterAndSortTable(queriesTable, currentTableData.queries, generateQueryRow, {
            search: document.getElementById('searchQueries').value,
            fromDate: document.getElementById('filter_q_from_date').value,
            toDate: document.getElementById('filter_q_to_date').value
        }, currentQueriesSortColumn, currentQueriesSortOrder);
    });

    document.getElementById('sortQueriesOldest').addEventListener('click', () => {
        currentQueriesSortColumn = 'queried_at';
        currentQueriesSortOrder = 'asc';
        filterAndSortTable(queriesTable, currentTableData.queries, generateQueryRow, {
            search: document.getElementById('searchQueries').value,
            fromDate: document.getElementById('filter_q_from_date').value,
            toDate: document.getElementById('filter_q_to_date').value
        }, currentQueriesSortColumn, currentQueriesSortOrder);
    });

    document.getElementById('sortQueriesReset').addEventListener('click', () => {
        currentQueriesSortColumn = 'queried_at'; // الافتراضي
        currentQueriesSortOrder = 'desc'; // الافتراضي
        document.getElementById('searchQueries').value = '';
        document.getElementById('filter_q_from_date').value = '';
        document.getElementById('filter_q_to_date').value = '';
        filterAndSortTable(queriesTable, currentTableData.queries, generateQueryRow, {}, currentQueriesSortColumn, currentQueriesSortOrder);
    });

    // حذف كل الاستعلامات (من سجل الاستعلامات)
    document.getElementById('deleteAllQueries').addEventListener('click', () => {
        confirmMessage.textContent = 'تحذير! هل أنت متأكد من حذف جميع سجلات الاستعلامات نهائيًا؟ لا يمكن التراجع عن هذا الإجراء.';
        confirmYesBtn.textContent = 'نعم، حذف كل الاستعلامات';
        currentConfirmAction = async () => {
            const result = await sendAjaxRequest('delete_all_queries', {});
            if (result.success) {
                showToast(result.message, 'success');
                await fetchAllLeaves(); // إعادة جلب وتحديث جميع الجداول
            }
        };
        confirmModal.show();
    });

    // عرض تفاصيل إجازة من سجل الاستعلامات
    queriesTable.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-view-leave-from-query')) {
            const leaveId = e.target.dataset.leaveId;
            showLoading();
            const result = await sendAjaxRequest('fetch_leave_details', {
                leave_id: leaveId
            });
            hideLoading();
            if (result.success && result.leave) {
                const leave = result.leave;
                leaveDetailsContainer.innerHTML = `
                    <p><strong>رمز الخدمة:</strong> ${htmlspecialchars(leave.service_code)}</p>
                    <p><strong>المريض:</strong> ${htmlspecialchars(leave.patient_name)} (${htmlspecialchars(leave.identity_number)})</p>
                    <p><strong>الطبيب:</strong> ${htmlspecialchars(leave.doctor_name)} (${htmlspecialchars(leave.doctor_title)})</p>
                    <p><strong>تاريخ الإصدار:</strong> ${htmlspecialchars(leave.issue_date)}</p>
                    <p><strong>بداية الإجازة:</strong> ${htmlspecialchars(leave.start_date)}</p>
                    <p><strong>نهاية الإجازة:</strong> ${htmlspecialchars(leave.end_date)}</p>
                    <p><strong>عدد الأيام:</strong> ${htmlspecialchars(leave.days_count)}</p>
                    <p><strong>نوع الإجازة:</strong> ${leave.is_companion == 1 ? 'مرافق: ' + htmlspecialchars(leave.companion_name) + ' (' + htmlspecialchars(leave.companion_relation) + ')' : 'أساسي'}</p>
                    <p><strong>مدفوعة:</strong> ${leave.is_paid == 1 ? 'نعم' : 'لا'}</p>
                    <p><strong>المبلغ:</strong> ${parseFloat(leave.payment_amount).toFixed(2)}</p>
                    <p><strong>تاريخ الإضافة:</strong> ${htmlspecialchars(leave.created_at)}</p>
                    <p><strong>تاريخ التعديل:</strong> ${htmlspecialchars(leave.updated_at || 'غير متوفر')}</p>
                    <p><strong>عدد الاستعلامات:</strong> ${leave.queries_count}</p>
                `;
                leaveDetailsModal.show();
            } else {
                showToast(result.message || 'فشل في جلب تفاصيل الإجازة.', 'danger');
            }
        }
    });

    // ====== أحداث الفرز، البحث لجدول المدفوعات لكل مريض ======

    // البحث
    document.getElementById('searchPayments').addEventListener('input', () => {
        filterAndSortTable(paymentsTable, currentTableData.payments, generatePaymentPatientRow, {
            search: document.getElementById('searchPayments').value
        }, 'name', 'asc'); // فرز افتراضي بالاسم مع البحث
    });
    document.getElementById('btn-search-payments').addEventListener('click', () => {
        document.getElementById('searchPayments').dispatchEvent(new Event('input'));
    });

    // الفرز
    document.getElementById('sortPaymentsPaid').addEventListener('click', () => {
        filterAndSortTable(paymentsTable, currentTableData.payments, generatePaymentPatientRow, {
            search: document.getElementById('searchPayments').value
        }, 'paid_amount', 'desc');
    });
    document.getElementById('sortPaymentsUnpaid').addEventListener('click', () => {
        filterAndSortTable(paymentsTable, currentTableData.payments, generatePaymentPatientRow, {
            search: document.getElementById('searchPayments').value
        }, 'unpaid_amount', 'desc');
    });
    document.getElementById('sortPaymentsReset').addEventListener('click', () => {
        filterAndSortTable(paymentsTable, currentTableData.payments, generatePaymentPatientRow, {}, 'name', 'asc'); // فرز افتراضي بالاسم
        document.getElementById('searchPayments').value = '';
    });

    // توليد صف لجدول المدفوعات لكل مريض
    function generatePaymentPatientRow(p) {
        return `
            <tr data-id="${p.id}">
                <td class="row-num"></td>
                <td>${htmlspecialchars(p.name)}</td>
                <td>${p.total}</td>
                <td>${p.paid_count}</td>
                <td>${p.unpaid_count}</td>
                <td>${parseFloat(p.paid_amount).toFixed(2)}</td>
                <td>${parseFloat(p.unpaid_amount).toFixed(2)}</td>
                <td><button class="btn btn-info btn-sm btn-view-patient-leaves" data-patient-id="${p.id}"><i class="bi bi-eye-fill"></i> عرض</button></td>
            </tr>
        `;
    }

    // عرض إجازات مريض معين من جدول المدفوعات
    paymentsTable.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-view-patient-leaves')) {
            const patientId = e.target.dataset.patientId;
            showLoading();
            // جلب الإجازات لهذا المريض فقط
            const result = await sendAjaxRequest('fetch_leaves_by_patient', {
                patient_id: patientId
            });
            hideLoading();
            if (result.success && result.leaves) {
                // عرض هذه الإجازات في مودال جديد أو في جدول مؤقت
                let tempModal = new bootstrap.Modal(document.getElementById('viewPatientLeavesModal') || createPatientLeavesModal());
                const modalBody = document.getElementById('viewPatientLeavesModalBody');
                modalBody.innerHTML = ''; // Clear previous content

                if (result.leaves.length > 0) {
                    const tableHtml = `
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover text-center" id="patientLeavesDetailTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>رقم</th>
                                        <th>رمز الخدمة</th>
                                        <th>الطبيب</th>
                                        <th>من</th>
                                        <th>إلى</th>
                                        <th>الأيام</th>
                                        <th>مدفوعة؟</th>
                                        <th>المبلغ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${result.leaves.map((leave, index) => `
                                        <tr>
                                            <td>${index + 1}</td>
                                            <td>${htmlspecialchars(leave.service_code)}</td>
                                            <td>${htmlspecialchars(leave.doctor_name)}</td>
                                            <td>${htmlspecialchars(leave.start_date)}</td>
                                            <td>${htmlspecialchars(leave.end_date)}</td>
                                            <td>${htmlspecialchars(leave.days_count)}</td>
                                            <td>${leave.is_paid == 1 ? '<span class="badge bg-success">نعم</span>' : '<span class="badge bg-danger">لا</span>'}</td>
                                            <td>${parseFloat(leave.payment_amount).toFixed(2)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                    modalBody.innerHTML = tableHtml;
                } else {
                    modalBody.innerHTML = '<p class="text-center">لا توجد إجازات لهذا المريض.</p>';
                }
                tempModal.show();
            } else {
                showToast(result.message || 'فشل في جلب إجازات المريض.', 'danger');
            }
        }
    });

    // دالة لإنشاء مودال لعرض إجازات المريض إذا لم يكن موجودًا
    function createPatientLeavesModal() {
        const modalElement = document.createElement('div');
        modalElement.className = 'modal fade';
        modalElement.id = 'viewPatientLeavesModal';
        modalElement.setAttribute('tabindex', '-1');
        modalElement.setAttribute('aria-hidden', 'true');

        modalElement.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">إجازات المريض</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body" id="viewPatientLeavesModalBody">
                        </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalElement);
        return modalElement;
    }


    // ====== وظائف الطباعة والتصدير ======
    document.getElementById('exportPDF').addEventListener('click', () => {
        exportTableToPdf(leavesTable, 'تقارير_الإجازات_النشطة.pdf', 'تقرير الإجازات المرضية النشطة');
    });

    document.getElementById('exportExcel').addEventListener('click', () => {
        exportTableToExcel(leavesTable, 'تقارير_الإجازات_النشطة.csv');
    });

    document.getElementById('printTable').addEventListener('click', () => {
        printTableContent(leavesTable, 'تقرير الإجازات المرضية النشطة');
    });


    // ====== جلب البيانات الأولية عند تحميل الصفحة ======
    // (يجب أن يتم تمرير هذه البيانات كمتغيرات JavaScript من PHP)
    currentTableData.leaves = initialLeaves;
    currentTableData.archived = initialArchived;
    currentTableData.queries = initialQueries;
    currentTableData.doctors = initialDoctors;
    currentTableData.patients = initialPatients;
    currentTableData.payments = initialPayments;
    currentTableData.notifications_payment = initialNotifications;


    // تحديث أرقام الصفوف للجداول الموجودة مسبقًا
    updateTable(leavesTable, currentTableData.leaves, generateLeaveRow);
    updateTable(archivedTable, currentTableData.archived, generateArchivedLeaveRow);
    updateTable(doctorsTable, currentTableData.doctors, generateDoctorRow);
    updateTable(patientsTable, currentTableData.patients, generatePatientRow);
    updateTable(queriesTable, currentTableData.queries, generateQueryRow);
    updateTable(paymentsTable, currentTableData.payments, generatePaymentPatientRow);
    updatePaymentNotifications(currentTableData.notifications_payment);

    // التأكد من تهيئة الحالة الأولية لحقول الإدخال اليدوي للمريض والطبيب
    togglePatientManualFields();
    toggleDoctorManualFields();

    // التأكد من تهيئة حالة حقول المرافق في نموذج الإضافة
    companionFields.forEach(field => {
        const inputElement = field.querySelector('input, textarea');
        if (companionCheckbox.checked) {
            field.classList.remove('hidden-field');
            if (inputElement) inputElement.setAttribute('required', 'required');
        } else {
            field.classList.add('hidden-field');
            if (inputElement) inputElement.removeAttribute('required');
        }
    });

});