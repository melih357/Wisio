# Wisio — WAMP Kurulum Paketi

Wisio, topluluk içinde doğru kişiyi bulmaya ve cevapları kalıcı bilgi kartlarına dönüştürmeye yönelik PHP + MySQL MVP uygulamasıdır.

## Klasör yapısı

- `backend/` PHP API ve veritabanı bağlantısı
- `frontend/` mobil uyumlu kullanıcı arayüzü
- `uploads/` güvenli dosya yükleme klasörü
- `.env` yerel ayarlar
- `database.sql` sıfırdan veritabanı ve demo veriler
- `migration.sql` sonraki sürüm örnek güncellemeleri

## WAMP üzerinde kurulum

1. ZIP dosyasını çıkartın.
2. Oluşan `Wisio` klasörünü `C:\wamp64\www\` içine kopyalayın.
3. WAMP simgesinin yeşil olduğundan emin olun.
4. `http://localhost/phpmyadmin/` adresini açın.
5. **İçe Aktar / Import** bölümünden `Wisio/database.sql` dosyasını seçip çalıştırın.
6. MySQL kullanıcı adı veya şifreniz farklıysa `Wisio/.env` dosyasını düzenleyin. Varsayılan WAMP ayarı: kullanıcı `root`, şifre boş.
7. Chrome'da şu adresi açın:

   `http://localhost/Wisio/frontend/`

## Demo giriş

- E-posta: `demo@wisio.local`
- Şifre: `password`

Not: Diğer örnek kullanıcıların şifreleri yalnızca veri görünümü içindir. Demo kullanıcıyla giriş yapın veya yeni hesap oluşturun.

## Test listesi

1. Ana sayfanın Wisio adı ve logosuyla açıldığını kontrol edin.
2. Demo hesabıyla giriş yapın.
3. Yeni topluluk oluşturun veya var olan topluluğa katılın.
4. Yeni soru sorun ve konu seçin.
5. Soru detayına girip cevap yazın.
6. Cevabı “İşime yaradı” olarak işaretleyin.
7. Cevabı bilgi kartına dönüştürün.
8. Yönetim panelindeki sayaçları kontrol edin.

## Teknik notlar

- PHP 8.0+ gerekir.
- `pdo_mysql`, `mbstring` ve `json` uzantıları açık olmalıdır.
- Üretim ortamında HTTPS, CSRF koruması, e-posta doğrulama, hız limiti ve ayrıntılı rol kontrolleri eklenmelidir.


## Revize 2 yenilikleri

- Kullanıcı kendi sorduğu soruya hem arayüzden hem API üzerinden cevap veremez.
- Soruyu soran kişi uzman eşleştirme listesinden otomatik çıkarılır.
- Soru gönderme alanı 3 adımlı, mobil uyumlu bir sihirbaza dönüştürüldü.
- Topluluk, konu, öncelik, beklenen cevap biçimi, yönlendirilecek kişi sayısı, görünürlük ve anonimlik seçilebilir.
- Kullanıcı yalnızca aktif üyesi olduğu toplulukta soru sorabilir.
- Göndermeden önce özet ve gizlilik kontrol ekranı gösterilir.
- Eski kurulum güncelleniyorsa phpMyAdmin üzerinden `migration.sql` dosyasını bir kez çalıştırın.


## Revize 2.1 - Oturum / Foreign Key düzeltmesi

Veritabanı yeniden içe aktarıldığında tarayıcıda kalan eski PHP oturumu otomatik temizlenir. `community_members` tablosunda 1452 foreign key hatası oluşmaz. Güncellemeden sonra sayfayı yenileyip tekrar giriş yapın.


## Revize 3 — Topluluk sistemi

Bu sürümde:

- Kullanıcıların Wisio'yu kullanabilmesi için en az bir aktif topluluğa üye olması zorunludur.
- Açık topluluklara anında katılım, onaylı/özel/kurumsal topluluklara yönetici onaylı katılım bulunur.
- Topluluk adına göre arama alanı eklendi.
- Her hesap için benzersiz `username` alanı eklendi; aynı kullanıcı adı ikinci kez alınamaz.
- Topluluk adları da benzersizdir.
- Roller: Yönetici, Moderatör, Bilgi Sorumlusu ve Üye.
- Yönetici bekleyen üyeleri onaylayabilir/reddedebilir ve rolleri değiştirebilir.
- Yönetici ve moderatör topluluk duyurusu yayınlayabilir ve sabitleyebilir.
- Topluluk detay sayfasında duyurular, üyeler ve roller görüntülenir.

### Mevcut veritabanını güncelleme

phpMyAdmin'de `wisio` veritabanını seçip bu paketteki `migration.sql` dosyasını içe aktarın. Ardından proje dosyalarını eski `C:\\wamp64\\www\\Wisio` klasörünün üzerine kopyalayın ve tarayıcıda oturumu kapatıp yeniden giriş yapın.

Yeni kurulumda yalnızca `database.sql` dosyasını içe aktarmanız yeterlidir.


## Revize 4 – isteğe bağlı topluluk şifresi

Topluluk yöneticileri katılım yöntemini **herkese açık**, **şifreyle katılım** veya **yönetici onaylı** olarak seçebilir. Şifreli topluluklarda parola PHP `password_hash` ile saklanır; açık metin olarak veritabanına yazılmaz. Yönetici, topluluk sayfasındaki **Katılım ayarları** düğmesinden yöntemi ve şifreyi daha sonra değiştirebilir.

## Revize 4.1 hata düzeltmesi

`Cannot read properties of undefined (reading 'filter')` hatası, eski veritabanı şeması nedeniyle API hata döndürdüğünde arayüzün boş dizi yerine tanımsız veri kullanmasından kaynaklanıyordu. Bu sürümde arayüz güvenli varsayılanlarla çalışır ve gerçek veritabanı hatasını ekranda gösterir.

Mevcut kurulum için phpMyAdmin'de `wisio` veritabanını seçip yalnızca `migration.sql` dosyasını içe aktarın. Bu geçiş dosyası WAMP üzerinde yaygın olan MySQL 5.7 ile uyumludur.

## Revize 4.2 SQL sözdizimi düzeltmesi

Önceki sürümde oturum kullanıcısını doğrulayan sorguda `status=\"active\"` ifadesi SQL'e ters eğik çizgilerle gönderiliyordu. Bazı MySQL/MariaDB sürümleri bunu `active` yakınında 1064 sözdizimi hatası olarak reddediyordu. Bu sürümde sorgu `status="active"` biçiminde düzeltilmiştir.

Bu hata için veritabanını yeniden içe aktarmak gerekmez. Proje dosyalarını değiştirip tarayıcıda Ctrl+F5 yapmak yeterlidir.
