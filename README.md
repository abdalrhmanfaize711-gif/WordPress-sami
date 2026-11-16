# رِحلات اليمن — WordPress + Strapi

موقع سياحي عربي باتجاه RTL. واجهة WordPress مكتوبة كإضافة PHP مستقلة، والمحتوى يُدار من Strapi 5. يأتي المشروع بإعداد محلي عبر Docker Compose وبيانات تجريبية وصور Wikimedia Commons مع نسب أصحابها وتراخيصها.

## ما الذي يأتي في المشروع؟

- `wordpress/wp-content/plugins/yemen-travel-site/` — الإضافة والواجهة العربية المتجاوبة.
- `strapi/` — تطبيق Strapi 5 ومخططات أنواع المحتوى.
- `docker-compose.yml` — WordPress وMySQL وStrapi وPostgreSQL للتطوير المحلي.
- `data/demo-content.json` — محتوى أولي قابل للإدخال في لوحة Strapi.
- `docs/SETUP-AR.md` — دليل تشغيل وإعداد خطوة بخطوة.
- `docs/IMAGE-CREDITS.md` — مصادر الصور والتراخيص ومصادر معلومات التراث.
- `scripts/package-plugin.ps1` — بناء ZIP قابل للرفع من لوحة WordPress.
- `scripts/validate-project.mjs` — فحوص بنية المشروع وبياناته.

## تشغيل سريع على Windows

1. ثبّت Docker Desktop وشغّله.
2. افتح PowerShell في مجلد المشروع.
3. أنشئ ملف إعداد محلي: `Copy-Item .env.example .env`.
4. ابدأ الخدمات: `docker compose up -d --build`.
5. افتح WordPress على `http://localhost:8080` وStrapi على `http://localhost:1337/admin`.
6. أكمل مع [دليل الإعداد العربي](docs/SETUP-AR.md) لإدخال بيانات الوجهات وربط الإضافة.

## بناء إضافة WordPress ZIP

```powershell
./scripts/package-plugin.ps1
```

ينشئ `dist/yemen-travel-site.zip` مع مجلد الإضافة في جذر الأرشيف ويتحقق من الملفات الأساسية.

## فحوص محلية

```powershell
node ./scripts/validate-project.mjs
docker compose config
```

فحوص PHP ودمج WordPress وStrapi كاملة تتطلب تشغيل Docker Desktop. إرشادات النشر العام وتأمين كلمات المرور والشهادات الاحتياطية موضحة في الدليل.

