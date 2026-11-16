# رِحلات اليمن — WordPress + Strapi على Laragon

موقع سياحي عربي باتجاه RTL. واجهة WordPress موجودة في إضافة PHP، ومحتوى الوجهات يُدار من Strapi 5. لا تحتاج إلى كتابة PHP. المسار المقترح للتشغيل المحلي هو Laragon على Windows؛ يوجد أيضًا إعداد Docker اختياري.

## تشغيله على Laragon

### المتطلبات

- Laragon مثبت في `C:\laragon` ويحتوي Apache وMySQL 8.4 وPHP 8.3 أو أحدث.
- Node.js بإصدار LTS 22 أو 24 مع npm.
- اتصال إنترنت لتنزيل WordPress للمرة الأولى والصور الخارجية.

### خطوات الإعداد

1. افتح Laragon واضغط **Start All**، وتأكد من تشغيل Apache وMySQL.
2. افتح PowerShell في مجلد المستودع. من سطح المكتب مثلًا:

   ```powershell
   cd "$env:USERPROFILE\Desktop\sam\WordPress-sami"
   powershell -ExecutionPolicy Bypass -File .\scripts\install-laragon.ps1
   ```

3. أدخل كلمة مرور MySQL root عند السؤال؛ اتركها فارغة واضغط Enter إن لم تكن قد غيّرتها في Laragon. سيُنزّل الإعداد WordPress إلى `C:\laragon\www\yemen-travel`، وينشئ قاعدتي البيانات، وينسخ الإضافة ويجهز إعداد Strapi المحلي.
4. افتح [http://localhost/yemen-travel](http://localhost/yemen-travel)، واختر العربية وأنشئ حساب مدير WordPress.
5. في PowerShell ثانٍ، شغّل Strapi:

   ```powershell
   cd "$env:USERPROFILE\Desktop\sam\WordPress-sami"
   powershell -ExecutionPolicy Bypass -File .\scripts\start-laragon-strapi.ps1
   ```

6. افتح [لوحة Strapi](http://127.0.0.1:1337/admin)، وأنشئ حساب مدير. في **Settings → API Tokens** أنشئ رمزًا بصلاحية **Read-only** واحتفظ به.
7. من WordPress فعّل إضافة **Yemen Travel — Yemen Tourism**. افتح **الإعدادات ← Yemen Travel**، واكتب عنوان Strapi `http://127.0.0.1:1337` ورمز القراءة، ثم احفظ.
8. أدخل وانشر إعداد الموقع والوجهات في Strapi. استخدم [البيانات التجريبية](data/demo-content.json) مرجعًا، واترك اعتماد كل صورة ومصدرها ورخصتها.
9. أنشئ صفحة «الرئيسية» في WordPress وأضف كتلة **Shortcode** فيها `[yemen_travel_home]`، ثم اجعلها صفحة البداية من **الإعدادات ← القراءة**. للصفحة المنفصلة استخدم `[yemen_destinations]`.

أوقف Strapi بـ `Ctrl+C`. لإيقاف Apache وMySQL استخدم **Stop All** في Laragon؛ بيانات الموقع وقواعد البيانات تبقى محفوظة.

دليل الإعداد المفصل وحل المشكلات: [docs/SETUP-AR.md](docs/SETUP-AR.md). مصادر الصور والتراخيص: [docs/IMAGE-CREDITS.md](docs/IMAGE-CREDITS.md).

## Docker (بديل)

ملف `docker-compose.yml` ما زال متاحًا للتشغيل عبر Docker Compose. راجع دليل الإعداد لخطواته. لا تحتاج إلى Docker إذا كنت تستخدم Laragon.

## بناء ملف إضافة WordPress ZIP

```powershell
.\scripts\package-plugin.ps1
```

ينشئ `dist/yemen-travel-site.zip`.