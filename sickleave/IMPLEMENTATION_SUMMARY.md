# ملخص التطوير - نظام إدارة الإجازات المرضية v3

## 📋 ملخص المشروع

تم تطوير نظام متكامل لإدارة الإجازات المرضية مع جميع الميزات المطلوبة وأكثر.

---

## ✅ الميزات المنجزة

### 1. ✔️ ربط المستشفيات بالأطباء
- **الحل**: جدول `hospitals` يحتوي على معرف فريد
- **الربط**: جدول `doctors` يحتوي على `hospital_id`
- **الفائدة**: عند اختيار مستشفى، يظهر فقط أطباؤها

### 2. ✔️ الأسماء الإنجليزية للأطباء والمرضى
- **الحقول المضافة**:
  - `doctors.name_en` - اسم الطبيب بالإنجليزية
  - `doctors.title_en` - المسمى الوظيفي بالإنجليزية
  - `patients.name_en` - اسم المريض بالإنجليزية
  - `hospitals.name_en` - اسم المستشفى بالإنجليزية
- **الخاصية**: إذا لم يتم إدخال الاسم الإنجليزي، يظهر فارغ

### 3. ✔️ تحويل التواريخ الهجرية تلقائياً
- **الدالة**: `gregorianToHijri()`
- **الآلية**: تحويل التاريخ الميلادي إلى هجري باستخدام خوارزمية رياضية
- **التطبيق**: يتم التحويل تلقائياً عند توليد PDF
- **الظهور**: يظهر التاريخ الهجري بجانب الميلادي في PDF

### 4. ✔️ حساب الأيام تلقائياً
- **الآلية**: حساب الفرق بين تاريخ البداية والنهاية
- **الصيغة**: `(end_date - start_date) + 1`
- **التخزين**: يتم حفظ النتيجة في `sick_leaves.days_count`

### 5. ✔️ إضافة "s" إلى كلمة "days" عند التعدد
- **الدالة**: `formatDaysCount()`
- **الآلية**:
  - إذا `days_count == 1`: عرض "1 day"
  - إذا `days_count > 1`: عرض "X days" (مع s)
- **التطبيق**: يتم التطبيق في PDF

### 6. ✔️ تحديد الأيام والتواريخ
- **الحقول المضافة**:
  - `sick_leaves.issue_date` - تاريخ الإصدار
  - `sick_leaves.issue_time` - وقت الإصدار
  - `sick_leaves.issue_period` - صباح/مساء (AM/PM)
  - `sick_leaves.start_date` - تاريخ البداية
  - `sick_leaves.end_date` - تاريخ النهاية
- **الواجهة**: يمكن تحديد كل هذه الحقول من لوحة التحكم

### 7. ✔️ إخفاء رقم الترخيص إذا لم يكن موجوداً
- **الحقل**: `hospitals.license_number` (اختياري)
- **الآلية**: في دالة `generatePdfHtml()`:
```php
$license_display = !empty($leave['license_number']) 
    ? 'رقم الترخيص : ' . $leave['license_number'] 
    : '';
```
- **النتيجة**: إذا كان فارغاً، لا يظهر في PDF

### 8. ✔️ رفع شعار المستشفى
- **الطريقة الأولى**: من رابط إنترنت
  - الدالة: `downloadLogoFromUrl()`
  - يتم حفظ الشعار محلياً في مجلد `logos/`
- **الطريقة الثانية**: من الجهاز (يمكن إضافتها لاحقاً)
- **التخزين**: يتم حفظ الرابط في `logo_url` والمسار المحلي في `logo_local_path`

### 9. ✔️ توليد PDF
- **الآلية**: دالة `generatePdfHtml()` تنشئ HTML احترافي
- **الخصائص**:
  - دعم اللغة العربية والإنجليزية
  - عرض التواريخ الميلادية والهجرية
  - عرض الأسماء بالعربية والإنجليزية
  - إخفاء رقم الترخيص إذا لم يكن موجوداً
  - حساب الأيام مع "s"
- **التحميل**: يتم التحميل مباشرة من الواجهة

### 10. ✔️ ربط لوحة التحكم بالقالب
- **الملف الأساسي**: `sick_leave_enhanced.php`
- **الواجهة**: تبويبات متعددة للإدارة
- **التكامل**: جميع العمليات متكاملة وتعمل بسلاسة

---

## 📁 هيكل الملفات

```
sick_leave_system/
├── sick_leave_enhanced.php      # الملف الرئيسي (لوحة التحكم + API)
├── index.php                     # نسخة بديلة (أساسية)
├── README.md                     # دليل شامل بالإنجليزية
├── SETUP_GUIDE_AR.md            # دليل التثبيت بالعربية
├── logos/                        # مجلد الشعارات (يتم إنشاؤه تلقائياً)
├── working.html                  # قالب HTML الأصلي
├── header.svg                    # شعار الرأس
├── sehalogoleft.svg             # شعار يسار
├── sehalogoright.svg            # شعار يمين
├── bottomright.svg              # شعار أسفل يمين
├── qr.svg                       # رمز QR
└── sickLeaves.svg               # أيقونة الإجازات
```

---

## 🗄️ هيكل قاعدة البيانات

### جدول `hospitals`
```sql
- id (INT, PK)
- name_ar (VARCHAR 200)
- name_en (VARCHAR 200)
- license_number (VARCHAR 50, NULL)
- logo_url (VARCHAR 500, NULL)
- logo_local_path (VARCHAR 500, NULL)
- created_at (DATETIME)
- updated_at (DATETIME)
```

### جدول `doctors`
```sql
- id (INT, PK)
- name_ar (VARCHAR 150)
- name_en (VARCHAR 150, NULL)
- title_ar (VARCHAR 150)
- title_en (VARCHAR 150, NULL)
- hospital_id (INT, FK, NULL)
- note (TEXT, NULL)
- created_at (DATETIME)
- updated_at (DATETIME)
```

### جدول `patients`
```sql
- id (INT, PK)
- name_ar (VARCHAR 150)
- name_en (VARCHAR 150, NULL)
- identity_number (VARCHAR 50, UNIQUE)
- phone (VARCHAR 30, NULL)
- folder_link (VARCHAR 500, NULL)
- created_at (DATETIME)
- updated_at (DATETIME)
```

### جدول `sick_leaves`
```sql
- id (INT, PK)
- service_code (VARCHAR 50, UNIQUE)
- patient_id (INT, FK)
- doctor_id (INT, FK)
- hospital_id (INT, FK, NULL)
- employer_ar (VARCHAR 200, NULL)
- employer_en (VARCHAR 200, NULL)
- issue_date (DATE)
- issue_time (VARCHAR 10, NULL)
- issue_period (VARCHAR 10, NULL) -- AM/PM
- start_date (DATE)
- end_date (DATE)
- days_count (INT)
- is_companion (TINYINT)
- companion_name (VARCHAR 150, NULL)
- companion_relation (VARCHAR 150, NULL)
- is_paid (TINYINT)
- payment_amount (DECIMAL 10,2)
- deleted_at (DATETIME, NULL)
- created_at (DATETIME)
- updated_at (DATETIME)
```

---

## 🔌 API Endpoints

### 1. إضافة مستشفى
```
POST /sick_leave_enhanced.php
Parameters:
- action: add_hospital
- name_ar: string
- name_en: string
- license_number: string (optional)
- logo_url: string (optional)

Response:
{
  "success": true/false,
  "message": "string",
  "hospital_id": int
}
```

### 2. إضافة طبيب
```
POST /sick_leave_enhanced.php
Parameters:
- action: add_doctor
- name_ar: string
- name_en: string (optional)
- title_ar: string
- title_en: string (optional)
- hospital_id: int (optional)

Response:
{
  "success": true/false,
  "message": "string",
  "doctor_id": int
}
```

### 3. إضافة مريض
```
POST /sick_leave_enhanced.php
Parameters:
- action: add_patient
- name_ar: string
- name_en: string (optional)
- identity_number: string
- phone: string (optional)

Response:
{
  "success": true/false,
  "message": "string",
  "patient_id": int
}
```

### 4. إنشاء إجازة
```
POST /sick_leave_enhanced.php
Parameters:
- action: create_leave
- patient_id: int
- doctor_id: int
- hospital_id: int (optional)
- employer_ar: string
- employer_en: string
- issue_date: date (YYYY-MM-DD)
- issue_time: time (HH:MM)
- issue_period: AM|PM
- start_date: date (YYYY-MM-DD)
- end_date: date (YYYY-MM-DD)

Response:
{
  "success": true/false,
  "message": "string",
  "leave_id": int,
  "service_code": "string"
}
```

### 5. جلب أطباء المستشفى
```
POST /sick_leave_enhanced.php
Parameters:
- action: get_hospital_doctors
- hospital_id: int (optional)

Response:
{
  "success": true/false,
  "doctors": [
    {
      "id": int,
      "name_ar": string,
      "name_en": string,
      "title_ar": string,
      "title_en": string
    }
  ]
}
```

### 6. جلب جميع المستشفيات
```
POST /sick_leave_enhanced.php
Parameters:
- action: get_hospitals

Response:
{
  "success": true/false,
  "hospitals": [...]
}
```

### 7. جلب جميع المرضى
```
POST /sick_leave_enhanced.php
Parameters:
- action: get_patients

Response:
{
  "success": true/false,
  "patients": [...]
}
```

### 8. جلب جميع الإجازات
```
POST /sick_leave_enhanced.php
Parameters:
- action: get_leaves

Response:
{
  "success": true/false,
  "leaves": [...]
}
```

### 9. توليد PDF
```
POST /sick_leave_enhanced.php
Parameters:
- action: generate_pdf
- leave_id: int

Response:
- Binary PDF file (or HTML if wkhtmltopdf not available)
```

---

## 🎨 الواجهة الرسومية

### التبويبات الرئيسية
1. **المستشفيات** - إدارة المستشفيات
2. **الأطباء** - إدارة الأطباء
3. **المرضى** - إدارة المرضى
4. **إصدار إجازة** - إنشاء إجازة جديدة
5. **قائمة الإجازات** - عرض وتحميل الإجازات

### الألوان المستخدمة
- **اللون الأساسي**: #306db5 (أزرق)
- **اللون الثانوي**: #2c3e77 (أزرق غامق)
- **اللون الخلفية**: #f5f5f5 (رمادي فاتح)

### الخطوط المستخدمة
- **العربية**: Noto Sans Arabic
- **الإنجليزية**: Times New Roman, Arial

---

## 🔐 الأمان

### إجراءات الأمان المطبقة
1. **Prepared Statements**: استخدام PDO مع prepared statements
2. **UTF-8**: تشفير كامل البيانات بـ UTF-8
3. **HTTPS Headers**: إضافة headers الأمان
4. **Session Security**: إعدادات آمنة للجلسات

---

## 📊 مثال على استخدام النظام

### سيناريو عملي:
1. **إضافة مستشفى**: "مستشفى الملك فهد" مع رقم ترخيص "4800031024"
2. **إضافة طبيب**: "محمد عبدالله" (استشاري) مرتبط بالمستشفى
3. **إضافة مريض**: "أسامة عبدالله" برقم هوية "1019874138"
4. **إصدار إجازة**:
   - المريض: أسامة عبدالله
   - الطبيب: محمد عبدالله
   - المستشفى: مستشفى الملك فهد
   - من: 2026-05-10 إلى 2026-05-12 (3 أيام)
   - الوقت: 09:00 صباحاً
5. **تحميل PDF**: يحتوي على:
   - رمز الإجازة: GSL260510xxxxx
   - المريض: أسامة عبدالله / Osama Abdullah
   - الطبيب: محمد عبدالله / Mohammed Abdullah
   - الأيام: 3 days (مع s)
   - التواريخ: 2026-05-10 إلى 2026-05-12 (ميلادي)
   - التواريخ الهجرية: 1447-11-13 إلى 1447-11-15

---

## 🚀 التحسينات المستقبلية

### يمكن إضافة:
1. نظام المستخدمين والصلاحيات
2. البحث والتصفية المتقدمة
3. التقارير والإحصائيات
4. الإشعارات البريدية
5. التوقيع الرقمي
6. النسخ الاحتياطية التلقائية
7. التصدير إلى Excel
8. تطبيق الهاتف المحمول

---

## 📝 ملاحظات مهمة

1. **قاعدة البيانات**: يتم إنشاء الجداول تلقائياً عند تشغيل الملف
2. **الشعارات**: يتم حفظها في مجلد `logos/` على الخادم
3. **PDF**: يتم توليده من HTML باستخدام wkhtmltopdf أو يتم عرضه كـ HTML
4. **التواريخ**: جميع التواريخ بصيغة YYYY-MM-DD
5. **الأوقات**: جميع الأوقات بصيغة 24 ساعة

---

## 📞 الدعم

للمساعدة أو الإبلاغ عن مشاكل، يرجى مراجعة:
- `README.md` - دليل شامل
- `SETUP_GUIDE_AR.md` - دليل التثبيت بالعربية

---

## 📄 الملفات المرفقة

1. **sick_leave_system_v3.zip** - الملف المضغوط الكامل
2. **sick_leave_enhanced.php** - الملف الرئيسي
3. **README.md** - الدليل الشامل
4. **SETUP_GUIDE_AR.md** - دليل التثبيت بالعربية
5. **IMPLEMENTATION_SUMMARY.md** - هذا الملف

---

**تم التطوير بنجاح! ✅**

**التاريخ**: 2026-05-10
**الإصدار**: 3.0
**الحالة**: جاهز للاستخدام
