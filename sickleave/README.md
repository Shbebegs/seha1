# نظام إدارة الإجازات المرضية - النسخة المحسّنة v3

## Sick Leave Management System - Enhanced Version v3

---

## 📋 نظرة عامة | Overview

نظام متكامل لإدارة الإجازات المرضية مع ميزات متقدمة تشمل:

An integrated system for managing sick leaves with advanced features including:

### ✨ الميزات الرئيسية | Main Features

1. **إدارة المستشفيات** | Hospital Management
   - إضافة المستشفيات بالعربية والإنجليزية
   - تخزين رقم الترخيص (اختياري)
   - رفع شعار المستشفى من الإنترنت أو من الجهاز
   - ربط الأطباء بالمستشفيات

2. **إدارة الأطباء** | Doctor Management
   - إضافة الأطباء بالأسماء العربية والإنجليزية
   - تحديد المسمى الوظيفي (عربي/إنجليزي)
   - ربط الأطباء بالمستشفيات
   - عرض قائمة الأطباء لكل مستشفى

3. **إدارة المرضى** | Patient Management
   - إضافة المرضى بالأسماء العربية والإنجليزية
   - تخزين رقم الهوية/الإقامة
   - حفظ معلومات الاتصال

4. **إصدار الإجازات المرضية** | Sick Leave Issuance
   - تحديد المريض والطبيب والمستشفى
   - تحديد جهة العمل (عربي/إنجليزي)
   - تحديد تاريخ الإصدار والوقت (صباح/مساء)
   - تحديد فترة الإجازة (من-إلى)
   - **حساب عدد الأيام تلقائياً**
   - **إضافة "s" إلى كلمة "days" عند تعدد الأيام**
   - **تحويل التواريخ الميلادية إلى هجرية تلقائياً**

5. **توليد PDF** | PDF Generation
   - توليد تقارير إجازات مرضية احترافية
   - عرض التواريخ بصيغتين: ميلادية وهجرية
   - عرض الأسماء بالعربية والإنجليزية
   - إخفاء رقم الترخيص إذا لم يكن موجوداً
   - تحميل الملف مباشرة

---

## 🗄️ بنية قاعدة البيانات | Database Structure

### جدول المستشفيات | Hospitals Table
```sql
- id: معرف فريد
- name_ar: اسم المستشفى بالعربية
- name_en: اسم المستشفى بالإنجليزية
- license_number: رقم الترخيص (اختياري)
- logo_url: رابط الشعار من الإنترنت
- logo_local_path: مسار الشعار المحلي
```

### جدول الأطباء | Doctors Table
```sql
- id: معرف فريد
- name_ar: اسم الطبيب بالعربية
- name_en: اسم الطبيب بالإنجليزية
- title_ar: المسمى الوظيفي بالعربية
- title_en: المسمى الوظيفي بالإنجليزية
- hospital_id: معرف المستشفى (اختياري)
```

### جدول المرضى | Patients Table
```sql
- id: معرف فريد
- name_ar: اسم المريض بالعربية
- name_en: اسم المريض بالإنجليزية
- identity_number: رقم الهوية/الإقامة
- phone: رقم الهاتف (اختياري)
```

### جدول الإجازات المرضية | Sick Leaves Table
```sql
- id: معرف فريد
- service_code: رمز الإجازة (فريد)
- patient_id: معرف المريض
- doctor_id: معرف الطبيب
- hospital_id: معرف المستشفى (اختياري)
- employer_ar: جهة العمل بالعربية
- employer_en: جهة العمل بالإنجليزية
- issue_date: تاريخ الإصدار
- issue_time: وقت الإصدار
- issue_period: صباح/مساء
- start_date: تاريخ بداية الإجازة
- end_date: تاريخ نهاية الإجازة
- days_count: عدد الأيام (محسوب تلقائياً)
```

---

## 🚀 التثبيت والتشغيل | Installation & Setup

### المتطلبات | Requirements
- PHP 7.4 أو أحدث
- MySQL 5.7 أو أحدث
- وصول إلى قاعدة البيانات

### خطوات التثبيت | Installation Steps

1. **نسخ الملفات**
```bash
cp sick_leave_enhanced.php /path/to/your/server/
```

2. **تحديث بيانات الاتصال بقاعدة البيانات**
```php
$db_host = 'your_host';
$db_user = 'your_user';
$db_pass = 'your_password';
$db_name = 'your_database';
```

3. **إنشاء مجلد الشعارات**
```bash
mkdir -p /path/to/server/logos
chmod 755 /path/to/server/logos
```

4. **فتح الملف في المتصفح**
```
http://localhost/sick_leave_enhanced.php
```

---

## 📱 واجهة المستخدم | User Interface

### التبويبات الرئيسية | Main Tabs

#### 1. المستشفيات | Hospitals
- إضافة مستشفى جديدة
- عرض قائمة المستشفيات
- تحديد الشعار والترخيص

#### 2. الأطباء | Doctors
- إضافة طبيب جديد
- ربط الطبيب بمستشفى
- عرض قائمة الأطباء

#### 3. المرضى | Patients
- إضافة مريض جديد
- تخزين البيانات الشخصية
- عرض قائمة المرضى

#### 4. إصدار إجازة | Issue Leave
- اختيار المستشفى (يعرض أطباؤها)
- اختيار الطبيب والمريض
- تحديد جهة العمل
- تحديد التواريخ والأوقات
- إصدار الإجازة

#### 5. قائمة الإجازات | Leaves List
- عرض جميع الإجازات المصدرة
- تحميل PDF لكل إجازة

---

## 🎯 الميزات المتقدمة | Advanced Features

### 1. تحويل التواريخ الهجرية | Hijri Date Conversion
```php
gregorianToHijri('2026-05-10')  // Returns: 13-11-1447
```
- تحويل تلقائي للتواريخ الميلادية إلى هجرية
- عرض التاريخ الهجري في تقرير PDF

### 2. حساب الأيام | Days Calculation
```php
// إذا كانت الإجازة يوم واحد: "1 day"
// إذا كانت أكثر من يوم: "3 days"
```
- حساب تلقائي لعدد الأيام
- إضافة "s" عند التعدد

### 3. إخفاء رقم الترخيص | Hide License Number
- إذا لم يكن هناك رقم ترخيص، لا يظهر في PDF
- عرض الترخيص فقط إذا كان موجوداً

### 4. الأسماء الثنائية | Bilingual Names
- عرض الأسماء بالعربية والإنجليزية
- إذا لم يكن هناك اسم إنجليزي، يظهر فارغ

### 5. توليد PDF | PDF Generation
- توليد PDF احترافي من البيانات
- دعم اللغة العربية والإنجليزية
- تحميل مباشر للملف

---

## 🔧 API Endpoints

### إضافة مستشفى | Add Hospital
```
POST /sick_leave_enhanced.php
action: add_hospital
name_ar: string
name_en: string
license_number: string (optional)
logo_url: string (optional)
```

### إضافة طبيب | Add Doctor
```
POST /sick_leave_enhanced.php
action: add_doctor
name_ar: string
name_en: string
title_ar: string
title_en: string
hospital_id: int (optional)
```

### إضافة مريض | Add Patient
```
POST /sick_leave_enhanced.php
action: add_patient
name_ar: string
name_en: string
identity_number: string
phone: string (optional)
```

### إنشاء إجازة | Create Leave
```
POST /sick_leave_enhanced.php
action: create_leave
patient_id: int
doctor_id: int
hospital_id: int (optional)
employer_ar: string
employer_en: string
issue_date: date
issue_time: time
issue_period: AM|PM
start_date: date
end_date: date
```

### توليد PDF | Generate PDF
```
POST /sick_leave_enhanced.php
action: generate_pdf
leave_id: int
```

---

## 📊 أمثلة الاستخدام | Usage Examples

### مثال 1: إضافة مستشفى
```javascript
const formData = new FormData();
formData.append('action', 'add_hospital');
formData.append('name_ar', 'مستشفى الملك فهد');
formData.append('name_en', 'King Fahd Hospital');
formData.append('license_number', '4800031024');
formData.append('logo_url', 'https://example.com/logo.png');

fetch('sick_leave_enhanced.php', {
    method: 'POST',
    body: formData
})
.then(r => r.json())
.then(data => console.log(data));
```

### مثال 2: إصدار إجازة
```javascript
const formData = new FormData();
formData.append('action', 'create_leave');
formData.append('patient_id', 1);
formData.append('doctor_id', 2);
formData.append('hospital_id', 1);
formData.append('employer_ar', 'الى من يهمه الامر');
formData.append('employer_en', 'TO WHOM IT MAY CONCERN');
formData.append('issue_date', '2026-05-10');
formData.append('issue_time', '09:00');
formData.append('issue_period', 'AM');
formData.append('start_date', '2026-05-10');
formData.append('end_date', '2026-05-12');

fetch('sick_leave_enhanced.php', {
    method: 'POST',
    body: formData
})
.then(r => r.json())
.then(data => console.log(data));
```

### مثال 3: تحميل PDF
```javascript
const formData = new FormData();
formData.append('action', 'generate_pdf');
formData.append('leave_id', 1);

fetch('sick_leave_enhanced.php', {
    method: 'POST',
    body: formData
})
.then(r => r.blob())
.then(blob => {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'sick_leave.pdf';
    a.click();
});
```

---

## 🎨 تخصيص التصميم | Customization

### تغيير الألوان | Change Colors
```css
/* اللون الأساسي */
#306db5  /* Blue */
#2c3e77  /* Dark Blue */

/* يمكن تعديل هذه الألوان في ملف CSS */
```

### تغيير الخطوط | Change Fonts
```css
font-family: 'Noto Sans Arabic', Arial, sans-serif;
```

---

## 🐛 استكشاف الأخطاء | Troubleshooting

### مشكلة: لا تظهر البيانات
**الحل:**
- تحقق من اتصال قاعدة البيانات
- تأكد من صحة بيانات الاتصال

### مشكلة: لا يعمل تحميل PDF
**الحل:**
- تأكد من تثبيت wkhtmltopdf
- أو استخدم الخادم الذي يدعم PDF

### مشكلة: الأسماء العربية تظهر بشكل خاطئ
**الحل:**
- تأكد من أن charset هو utf8mb4
- تحقق من ترميز الملف

---

## 📝 ملاحظات مهمة | Important Notes

1. **الأمان**: تأكد من استخدام HTTPS في الإنتاج
2. **النسخ الاحتياطية**: قم بعمل نسخ احتياطية منتظمة لقاعدة البيانات
3. **الصلاحيات**: تأكد من صلاحيات المجلدات (755 للمجلدات، 644 للملفات)
4. **الأداء**: قم بإضافة indexes لقاعدة البيانات للأداء الأفضل

---

## 📞 الدعم | Support

للمساعدة أو الإبلاغ عن مشاكل، يرجى التواصل مع فريق الدعم.

For support or bug reports, please contact the support team.

---

## 📄 الترخيص | License

هذا المشروع مرخص تحت MIT License

This project is licensed under MIT License

---

**آخر تحديث | Last Updated:** 2026-05-10
**الإصدار | Version:** 3.0
