<<<<<<<< Update Guide >>>>>>>>>>>

Fresh Installation Guide
Immediate Older Version: v5.6.0 Current Version: v5.7.0

## Feature Update
1. Update htaccess Files For Enhance Site Security
2. Use config Instead Of Using env helper
3. Added Sitemap Generate Feature On Admin Panel  
4. Added File Permission Monitor And Restore Option On Admin Panel
5. Added Storage Relink/Repair Option On Admin Panel
6. 2FA Improvement Admin & Users Panel
7. Updated Strowallet Virtual Card System
8. Reachead Mail System Added
9. Updated FileHolder CDN Link
10. Added Email Template Manager (Edit & Preview) On Admin Panel
11. Updated SMS Template Manager With Groups & Preview On Admin Panel
12. Improved Admin, User, Agent & Merchant Dashboard Performance (Daily Stats & DB Indexes)
13. Updated User Panel UI/UX (Dashboard Overview, Limit Meter, Gateway Select, Payment Summary)
14. Updated Gift Card System & UI (Card List, Search, Details & Order View)



Command For Fresh Install:

1. composer update --no-dev
2. php artisan migrate:fresh --seed
3. php artisan passport:keys --force
4. php artisan passport:client --personal --no-interaction --provider=users
5. php artisan passport:client --personal --no-interaction --provider=merchants
6. php artisan passport:client --personal --no-interaction --provider=agents
7. php artisan storage:link


Command For Update From v5.6.0 To v5.7.0:

1. composer update
2. php artisan migrate
3. php artisan db:seed --class=Database\\Seeders\\Update\\VersionUpdateSeeder
4. php artisan optimize:clear

