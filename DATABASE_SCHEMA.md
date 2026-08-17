# DATABASE SCHEMA - ICOMAN 2026 CMS

Catatan: kolom translatable (EN/ID) disimpan sebagai JSON via `spatie/laravel-translatable`, ditandai `(T)`.

## editions
Antisipasi multi-tahun (ICOMAN 2026, 2027, dst.)
| Kolom | Tipe | Ket |
|---|---|---|
| id | bigint pk | |
| name | string | "ICOMAN 2026" |
| theme (T) | json | tagline/tema tahun ini |
| start_date | date | |
| end_date | date | |
| is_active | boolean | edition yang tampil di frontend |

## site_settings (via spatie/laravel-settings, bukan tabel biasa)
- conference_name, logo, favicon
- primary_color, secondary_color
- contact_email, contact_whatsapp, contact_address
- social_instagram, social_twitter, social_youtube
- google_maps_embed_url
- default_locale (en/id)

## pages
Untuk halaman CMS bebas: About, Venue, Author Guidelines, dsb.
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk nullable (beberapa page bisa global, tidak per-edition) |
| slug | string unique |
| title (T) | json |
| content (T) | json (rich text) |
| meta_title (T) | json |
| meta_description (T) | json |
| is_published | boolean |

## committees
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| category | enum(steering, organizing, scientific, reviewer) |
| name | string |
| role_title (T) | json — mis. "Chairman", "Sekretaris" |
| affiliation | string |
| photo | media (spatie collection) |
| order | integer |

## speakers
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| type | enum(keynote, invited, plenary) |
| name | string |
| title_degree | string — mis. "Prof. Dr." |
| affiliation | string |
| country | string |
| topic (T) | json |
| bio (T) | json |
| photo | media |
| order | integer |

## topics
Scope/topik Call for Papers
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| title (T) | json |
| order | integer |

## important_dates
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| label (T) | json — "Full Paper Submission Deadline" |
| date | date |
| is_highlighted | boolean |
| order | integer |

## registration_fees
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| category (T) | json — "Presenter (Domestic)" |
| price_early_bird | decimal nullable |
| price_regular | decimal |
| currency | string default IDR |
| notes (T) | json nullable |
| order | integer |

## schedules
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| day_date | date |
| time_start | time |
| time_end | time |
| title (T) | json |
| speaker_name | string nullable |
| room | string nullable |
| session_type | enum(plenary, parallel, break, registration, other) |

## downloads
Template paper, panduan penulisan, dsb.
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk nullable |
| title (T) | json |
| category | enum(template, guideline, other) |
| file | media |
| order | integer |

## sponsors
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| name | string |
| tier | enum(platinum, gold, silver, partner, media_partner) |
| logo | media |
| website_url | string nullable |
| order | integer |

## news
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk nullable |
| slug | string unique |
| title (T) | json |
| excerpt (T) | json |
| content (T) | json |
| thumbnail | media |
| published_at | datetime |
| is_published | boolean |

## galleries
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| image | media |
| caption (T) | json nullable |
| order | integer |

## faqs
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk nullable |
| question (T) | json |
| answer (T) | json |
| order | integer |

## contact_messages
Simpan submission form kontak publik (bukan translatable — data isian user)
| Kolom | Tipe |
|---|---|
| id | pk |
| name | string |
| email | string |
| subject | string nullable |
| message | text |
| is_read | boolean default false |
| created_at | timestamp |

## users
Dua populasi berbeda dibedakan lewat `guard`/role, TIDAK lewat tabel terpisah:
- **Admin/Panitia/Reviewer** → guard `web`, role dari Spatie Permission (`superadmin`, `content_admin`, `reviewer`). Login via Filament.
- **Author/Peserta** → guard `author`, tabel `authors` terpisah (lihat di bawah) supaya tidak campur dengan tabel `users` milik admin.

## authors
Akun publik untuk submission & registrasi (guard `author`, BUKAN tabel `users` Filament).
| Kolom | Tipe |
|---|---|
| id | pk |
| name | string |
| email | string unique |
| password | string (hashed) |
| affiliation | string |
| country | string |
| phone | string nullable |
| email_verified_at | timestamp nullable |

## submissions
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| author_id | fk → authors (submitter/corresponding utama) |
| topic_id | fk nullable |
| submission_number | string unique (auto-generate, mis. `ICOMAN2026-0001`) |
| title | string |
| abstract | text |
| abstract_id | text nullable (versi Indonesia, opsional) |
| file | media (collection `paper`, mime docx/pdf) |
| camera_ready_file | media nullable (collection `camera_ready`) |
| status | enum(submitted, under_review, revision_required, accepted, rejected) default submitted |
| submitted_at | timestamp |

## submission_authors
Co-author (bisa lebih dari satu; submitter utama juga tercatat di sini dengan `is_corresponding=true`)
| Kolom | Tipe |
|---|---|
| id | pk |
| submission_id | fk |
| name | string |
| email | string |
| affiliation | string |
| is_corresponding | boolean |
| order | integer |

## review_assignments
| Kolom | Tipe |
|---|---|
| id | pk |
| submission_id | fk |
| reviewer_id | fk → users (guard web, role `reviewer`) |
| assigned_at | timestamp |
| status | enum(pending, completed) default pending |

## reviews
| Kolom | Tipe |
|---|---|
| id | pk |
| review_assignment_id | fk |
| score | integer nullable (skala ditentukan panitia, mis. 1–100) |
| comments_for_author | text |
| comments_for_committee | text nullable (catatan internal, tidak dilihat author) |
| recommendation | enum(accept, minor_revision, major_revision, reject) |
| submitted_at | timestamp |

## registrations
| Kolom | Tipe |
|---|---|
| id | pk |
| edition_id | fk |
| author_id | fk → authors (peserta bisa juga bukan pemakalah — tetap pakai akun `authors` yang sama, cukup tanpa relasi ke `submissions`) |
| registration_fee_id | fk → registration_fees |
| submission_id | fk nullable (jika registrasi terkait paper tertentu) |
| payment_method | enum(manual, gateway) |
| amount | decimal |
| status | enum(pending, pending_verification, paid, failed) default pending |
| proof_file | media nullable (collection `payment_proof`, khusus payment_method=manual) |
| gateway_transaction_id | string nullable |
| gateway_payload | json nullable (simpan raw response terakhir dari gateway untuk audit) |
| paid_at | timestamp nullable |

## payments
Log setiap percobaan/transaksi pembayaran (memungkinkan retry pada `registrations` yang sama)
| Kolom | Tipe |
|---|---|
| id | pk |
| registration_id | fk |
| method | enum(manual, gateway) |
| gateway_name | string nullable — "midtrans"/"xendit" |
| gateway_reference | string nullable |
| amount | decimal |
| status | enum(initiated, success, failed) |
| raw_response | json nullable |
| created_at | timestamp |

---

### Catatan implementasi
- Semua tabel dengan `order` integer dipakai untuk drag-sort di Filament (`Reorderable` trait / `SpatieMediaLibraryImageColumn` + `Tables\Actions`).
- Semua tabel dengan `edition_id` di-scope otomatis ke edition aktif di query publik via Global Scope atau helper `currentEdition()`.
- Field `(T)` dibuat via trait `HasTranslations` pada model + cast `array`/json pada migration.
- Tabel `submissions`, `submission_authors`, `review_assignments`, `reviews`, `registrations`, `payments` TIDAK translatable — ini data transaksional/isian pengguna, bukan konten CMS.
- `authors.password` wajib di-hash, dan guard `author` harus dikonfigurasi terpisah di `config/auth.php` (jangan pakai guard default `web` yang dipakai admin Filament).
- Status pada `submissions.status` sebaiknya diupdate via satu method terpusat (mis. `Submission::changeStatus()`) yang juga men-trigger notifikasi email — jangan update kolom `status` langsung dari banyak tempat berbeda.
