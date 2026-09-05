<?php

namespace Database\Seeders;

use App\Models\Committee;
use App\Models\Edition;
use App\Models\Faq;
use App\Models\ImportantDate;
use App\Models\Page;
use App\Models\RegistrationFee;
use App\Models\Schedule;
use App\Models\Speaker;
use App\Models\Topic;
use App\Settings\SiteSettings;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        $edition = Edition::firstOrCreate(['name' => 'ICOMAN 2026'], ['is_active' => true]);
        $edition->update([
            'theme' => [
                'en' => 'Navigating Business Resilience and Inclusive Sustainable Development in the AI Era',
                'id' => 'Menavigasi Ketahanan Bisnis dan Pembangunan Berkelanjutan Inklusif di Era AI',
            ],
            'start_date' => '2026-11-07 09:00:00',
            'end_date' => '2026-11-07 16:00:00',
            'is_active' => true,
        ]);
        $eid = $edition->id;

        // 1. Site Settings
        $s = app(SiteSettings::class);
        $s->conference_name = 'ICOMAN 2026';
        $s->contact_email = 'icoman@unm.ac.id';
        $s->contact_whatsapp = '+62 812-4261-2026';
        $s->contact_address = 'Faculty of Economics and Business, Universitas Negeri Makassar, Jl. Raya Pendidikan, Makassar, South Sulawesi, Indonesia';
        $s->event_location = 'Online via Zoom Meeting';
        $s->event_mode = 'Online (Fully Online / Virtual Conference)';
        $s->organizer_name = 'Department of Management, Faculty of Economics and Business, Universitas Negeri Makassar';
        $s->default_locale = 'en';
        $s->bank_name = 'Bank BNI';
        $s->bank_account_number = '08122026001';
        $s->bank_account_holder = 'Panitia ICOMAN UNM';
        $s->save();

        // 2. Topics / 4 Main Sub-themes
        $subthemes = [
            [
                'en' => 'Sub-theme 1: Inclusive HR Resilience and Talent Sustainability in the Age of AI and Digital Innovation',
                'id' => 'Sub-tema 1: Ketahanan SDM Inklusif dan Keberlanjutan Talenta di Era AI dan Inovasi Digital',
            ],
            [
                'en' => 'Sub-theme 2: AI-Driven Experiential Marketing and Inclusive Sustainable Consumer Behavior',
                'id' => 'Sub-tema 2: Pemasaran Berbasis Pengalaman Berbasis AI dan Perilaku Konsumen Berkelanjutan yang Inklusif',
            ],
            [
                'en' => 'Sub-theme 3: Financial Inclusion, Green Investment, and Risk Management in the AI Era',
                'id' => 'Sub-tema 3: Inklusi Keuangan, Investasi Hijau, dan Manajemen Risiko di Era AI',
            ],
            [
                'en' => 'Sub-theme 4: Strategic Agility and Inclusive Business Models for Sustainable Corporate Resilience',
                'id' => 'Sub-tema 4: Agilitas Strategis dan Model Bisnis Inklusif untuk Ketahanan Korporasi Berkelanjutan',
            ],
        ];
        $existingTopics = Topic::where('edition_id', $eid)->orderBy('id')->get();
        foreach ($subthemes as $i => $title) {
            if (isset($existingTopics[$i])) {
                $existingTopics[$i]->update(['title' => $title, 'order' => $i + 1]);
            } else {
                Topic::create(['edition_id' => $eid, 'title' => $title, 'order' => $i + 1]);
            }
        }

        // 3. Important Dates
        ImportantDate::where('edition_id', $eid)->delete();
        $dates = [
            [
                'label' => [
                    'en' => 'Abstract Submission Deadline',
                    'id' => 'Batas Pengumpulan Abstrak',
                ],
                'date' => '2026-09-15',
                'is_highlighted' => true,
            ],
            [
                'label' => [
                    'en' => 'Notification of Acceptance',
                    'id' => 'Pengumuman Penerimaan (Acceptance)',
                ],
                'date' => '2026-10-10',
                'is_highlighted' => false,
            ],
            [
                'label' => [
                    'en' => 'Extended Abstract & Registration Payment Deadline',
                    'id' => 'Batas Input Extended Abstract & Pembayaran',
                ],
                'date' => '2026-10-25',
                'is_highlighted' => false,
            ],
            [
                'label' => [
                    'en' => 'Conference Day (Online Zoom)',
                    'id' => 'Hari Pelaksanaan Konferensi (Zoom Online)',
                ],
                'date' => '2026-11-07',
                'is_highlighted' => true,
            ],
        ];
        foreach ($dates as $i => $d) {
            ImportantDate::create([
                'edition_id' => $eid,
                'label' => $d['label'],
                'date' => $d['date'],
                'is_highlighted' => $d['is_highlighted'],
                'order' => $i + 1,
            ]);
        }

        // 4. Schedules / Rundown (7 November 2026)
        Schedule::where('edition_id', $eid)->delete();
        $rundown = [
            [
                'time_start' => '09:00',
                'time_end' => '09:30',
                'title' => [
                    'en' => 'Preparation and Technical Check',
                    'id' => 'Persiapan dan Pengecekan Teknis',
                ],
                'speaker_name' => 'MC & Technical Team',
                'room' => 'Main Zoom Room',
                'session_type' => 'registration',
            ],
            [
                'time_start' => '09:30',
                'time_end' => '09:50',
                'title' => [
                    'en' => 'Opening Ceremony',
                    'id' => 'Upacara Pembukaan',
                ],
                'speaker_name' => 'MC & Technical Team',
                'room' => 'Main Zoom Room',
                'session_type' => 'other',
            ],
            [
                'time_start' => '09:50',
                'time_end' => '10:00',
                'title' => [
                    'en' => 'Welcome Remarks',
                    'id' => 'Sambutan Pembuka',
                ],
                'speaker_name' => 'Dean of FEB UNM / Head of Management Department',
                'room' => 'Main Zoom Room',
                'session_type' => 'other',
            ],
            [
                'time_start' => '10:00',
                'time_end' => '10:15',
                'title' => [
                    'en' => 'Keynote Speech & Official Opening',
                    'id' => 'Keynote Speech & Pembukaan Resmi',
                ],
                'speaker_name' => 'Rector of Universitas Negeri Makassar',
                'room' => 'Main Zoom Room',
                'session_type' => 'plenary',
            ],
            [
                'time_start' => '10:15',
                'time_end' => '12:00',
                'title' => [
                    'en' => 'International Panel Session (5 Keynote Speakers + 30-min Q&A)',
                    'id' => 'Sesi Panel Internasional (5 Pembicara Utama + 30 Menit Tanya Jawab)',
                ],
                'speaker_name' => 'Keynote Speakers & Moderator',
                'room' => 'Main Zoom Room',
                'session_type' => 'plenary',
            ],
            [
                'time_start' => '12:00',
                'time_end' => '13:00',
                'title' => [
                    'en' => 'Break & Parallel Breakout Room Setup',
                    'id' => 'Istirahat & Persiapan Ruang Paralel',
                ],
                'speaker_name' => 'Technical Team',
                'room' => 'Main Zoom & Breakout Rooms',
                'session_type' => 'break',
            ],
            [
                'time_start' => '13:00',
                'time_end' => '15:10',
                'title' => [
                    'en' => 'Parallel Presentation Sessions (200 Presenters across 20 Rooms)',
                    'id' => 'Sesi Presentasi Paralel (200 Pemakalah di 20 Ruang Breakout)',
                ],
                'speaker_name' => 'Session Presenters & Discussants / Reviewers',
                'room' => 'Breakout Rooms 1–20',
                'session_type' => 'parallel',
            ],
        ];
        foreach ($rundown as $i => $item) {
            Schedule::create([
                'edition_id' => $eid,
                'day_date' => '2026-11-07',
                'time_start' => $item['time_start'],
                'time_end' => $item['time_end'],
                'title' => $item['title'],
                'speaker_name' => $item['speaker_name'],
                'room' => $item['room'],
                'session_type' => $item['session_type'],
                'order' => $i + 1,
            ]);
        }

        // 5. CMS Pages
        Page::updateOrCreate(
            ['slug' => 'about'],
            [
                'edition_id' => $eid,
                'title' => [
                    'en' => 'About ICOMAN 2026',
                    'id' => 'Tentang ICOMAN 2026',
                ],
                'content' => [
                    'en' => '<h3>Navigating Business Resilience and Inclusive Sustainable Development in the AI Era</h3>
<p>The <strong>International Conference of Management 2026 (ICOMAN 2026)</strong> is organized by Universitas Negeri Makassar (Faculty of Economics and Business) as an international academic forum dedicated to bridging the gap between theory and practice, academic research and industry needs, and technological innovation and social responsibility.</p>

<h4>1. Background and Urgency (Rationale)</h4>
<p>Entering the mid-2020s, the global business landscape is undergoing unprecedented structural transformation. The accelerating adoption of generative and agentic Artificial Intelligence (AI) across organizational functions—from human resource management, marketing, and finance to strategic governance—is fundamentally reshaping how organizations create value, compete, and survive in volatile (VUCA) and brittle (BANI) business environments. In 2026, businesses are no longer asking "whether" to adopt AI, but "how" to integrate it ethically, inclusively, and sustainably.</p>
<p>At the same time, global pressure for sustainable development (the Sustainable Development Goals/SDGs, ISSB sustainability reporting regulations, and ESG frameworks) continues to intensify. This creates a dual challenge for both business practitioners and academics: how to harness the speed and efficiency offered by AI without sacrificing the principles of inclusivity, social justice, and environmental sustainability—particularly in developing countries and emerging markets such as ASEAN.</p>

<h4>2. Strategic Objectives</h4>
<ul>
  <li><strong>Academic Objective:</strong> Provide an international scholarly forum to disseminate the latest research findings on AI integration, business resilience, and inclusive sustainable development.</li>
  <li><strong>Collaborative and Research Networking Objective:</strong> Build and expand cross-country, cross-institutional research networks among universities, research institutions, and industry partners.</li>
  <li><strong>Practical and Applied Objective:</strong> Bridge the academic–practitioner gap through knowledge exchange on the responsible application of AI in real business contexts.</li>
  <li><strong>Policy Impact Objective:</strong> Formulate evidence-based policy recommendations for policymakers and regulators to foster an inclusive, resilient, and sustainable business ecosystem.</li>
  <li><strong>Scientific Publication Objective:</strong> Facilitate the publication of high-quality research in indexed proceedings and reputable partner journals.</li>
</ul>

<h4>3. Target Audience</h4>
<ul>
  <li><strong>Academics:</strong> Lecturers, researchers, and scholars from universities specializing in management, business, economics, and applied social sciences.</li>
  <li><strong>Graduate Students:</strong> Master\'s (S2) and Doctoral (S3) students working on theses/dissertations related to the conference theme.</li>
  <li><strong>Business Practitioners:</strong> Executives, managers, management consultants, and industry professionals in digital transformation, HR, marketing, and finance.</li>
  <li><strong>Policy Makers:</strong> Representatives of government institutions, regulators, and international organizations involved in economic and business policy.</li>
  <li><strong>Researchers and Think Tanks:</strong> Independent research institutions focused on sustainability, inclusivity, and technology issues.</li>
  <li><strong>Industry Partners & Investors:</strong> Representatives of corporations, startups, and investors interested in AI-driven innovation and sustainable investment.</li>
</ul>

<h4>4. Expected Outputs</h4>
<ul>
  <li><strong>Special Issue in Partner Journals:</strong> Opportunities for top-tier papers to be recommended for publication in partner journal special issues.</li>
  <li><strong>Policy Brief:</strong> A summary of strategic policy recommendations synthesized from panel discussions and presentations.</li>
  <li><strong>International Network Expansion:</strong> Institutional partnerships and research collaborations (MoU/MoA).</li>
  <li><strong>Best Paper Award:</strong> Formal recognition for outstanding scientific contribution in each sub-theme.</li>
  <li><strong>International Certificates:</strong> Official certificates of participation and presentation for all registered attendees.</li>
</ul>',
                    'id' => '<h3>Menavigasi Ketahanan Bisnis dan Pembangunan Berkelanjutan Inklusif di Era AI</h3>
<p><strong>International Conference of Management 2026 (ICOMAN 2026)</strong> diselenggarakan oleh Universitas Negeri Makassar (Fakultas Ekonomi dan Bisnis) sebagai forum ilmiah internasional yang menjembatani kesenjangan antara teori dan praktik, riset akademis dan kebutuhan industri, serta inovasi teknologi dan tanggung jawab sosial.</p>

<h4>1. Latar Belakang dan Urgensi</h4>
<p>Memasuki pertengahan dekade 2020-an, lanskap bisnis global mengalami transformasi struktural yang belum pernah terjadi sebelumnya. Akselerasi adopsi Kecerdasan Buatan (Artificial Intelligence/AI) generatif dan agentik di berbagai fungsi organisasi—mulai dari manajemen sumber daya manusia, pemasaran, keuangan, hingga tata kelola strategis—secara fundamental membentuk ulang cara organisasi menciptakan nilai, bersaing, dan bertahan di iklim bisnis yang VUCA (Volatile, Uncertain, Complex, Ambiguous) dan BANI (Brittle, Anxious, Nonlinear, Incomprehensible). Di tahun 2026, dunia bisnis tidak lagi bertanya "apakah" perlu mengadopsi AI, melainkan "bagaimana" mengintegrasikannya secara etis, inklusif, dan berkelanjutan.</p>
<p>Di saat yang sama, tuntutan global terhadap pembangunan berkelanjutan (Sustainable Development Goals/SDGs, standar ISSB, dan kerangka ESG) kian menguat. Fenomena ini menghadirkan tantangan ganda bagi praktisi maupun akademisi: bagaimana memanfaatkan kecepatan dan efisiensi AI tanpa mengorbankan prinsip inklusivitas, keadilan sosial, dan kelestarian lingkungan—khususnya di kawasan ASEAN dan negara berkembang.</p>

<h4>2. Tujuan Strategis</h4>
<ul>
  <li><strong>Tujuan Akademik:</strong> Menyediakan forum ilmiah internasional untuk diseminasi temuan riset mutakhir terkait integrasi AI, ketahanan bisnis, dan pembangunan berkelanjutan inklusif.</li>
  <li><strong>Tujuan Kolaborasi & Jejaring Riset:</strong> Membangun dan memperluas jaringan riset internasional lintas negara dan institusi antara perguruan tinggi, lembaga riset, dan mitra industri.</li>
  <li><strong>Tujuan Praktis & Terapan:</strong> Menjembatani kesenjangan akademisi–praktisi melalui pertukaran wawasan penerapan AI yang bertanggung jawab dalam dunia bisnis.</li>
  <li><strong>Tujuan Dampak Kebijakan:</strong> Merumuskan rekomendasi kebijakan strategis berbasis bukti bagi regulator untuk ekosistem bisnis era AI.</li>
  <li><strong>Tujuan Publikasi Ilmiah:</strong> Memfasilitasi publikasi hasil penelitian berkualitas tinggi melalui prosiding terindeks dan jurnal mitra bereputasi.</li>
</ul>

<h4>3. Sasaran Peserta</h4>
<ul>
  <li><strong>Akademisi:</strong> Dosen dan peneliti dari perguruan tinggi dalam dan luar negeri di bidang manajemen, bisnis, ekonomi, dan ilmu sosial terapan.</li>
  <li><strong>Mahasiswa Pascasarjana:</strong> Mahasiswa S2 (Magister) dan S3 (Doktoral) yang sedang menyusun tesis/disertasi terkait tema konferensi.</li>
  <li><strong>Praktisi Bisnis:</strong> Eksekutif, manajer, konsultan manajemen, dan profesional industri di bidang transformasi digital, SDM, pemasaran, dan keuangan.</li>
  <li><strong>Pembuat Kebijakan:</strong> Perwakilan instansi pemerintah, regulator, dan organisasi internasional pengampu kebijakan ekonomi dan bisnis.</li>
  <li><strong>Peneliti & Think Tank:</strong> Lembaga riset independen yang mengkaji isu keberlanjutan, inklusi, dan teknologi.</li>
  <li><strong>Mitra Industri & Investor:</strong> Perwakilan korporasi, startup, dan investor yang tertarik pada inovasi bisnis AI dan investasi berkelanjutan.</li>
</ul>

<h4>4. Luaran yang Diharapkan</h4>
<ul>
  <li><strong>Special Issue Jurnal Mitra:</strong> Rekomendasi publikasi edisi khusus pada jurnal mitra bereputasi bagi naskah berkualitas tinggi.</li>
  <li><strong>Policy Brief:</strong> Ringkasan rekomendasi kebijakan strategis hasil sintesis diskusi panel dan presentasi riset.</li>
  <li><strong>Perluasan Jejaring Internasional:</strong> Kolaborasi riset dan kemitraan kelembagaan (MoU/MoA).</li>
  <li><strong>Best Paper Award:</strong> Apresiasi atas karya ilmiah terbaik dan orisinal di setiap sub-tema.</li>
  <li><strong>Sertifikat Internasional:</strong> Sertifikat kepesertaan/presentasi resmi bagi seluruh peserta terdaftar.</li>
</ul>',
                ],
                'is_published' => true,
            ],
        );

        Page::updateOrCreate(
            ['slug' => 'venue'],
            [
                'edition_id' => $eid,
                'title' => [
                    'en' => 'Event Format & Virtual Platform',
                    'id' => 'Format Acara & Platform Virtual',
                ],
                'content' => [
                    'en' => '<h3>Fully Online / Virtual Conference via Zoom</h3>
<p>To ensure accessible participation for attendees, presenters, and scholars from various countries without geographical barriers, while also reflecting our sustainability commitment to minimizing carbon footprints, <strong>ICOMAN 2026 will be held fully online via the Zoom Meeting platform</strong>.</p>

<h4>Session Formats</h4>
<ul>
  <li><strong>Keynote Speech & Plenary Panel Session:</strong> Conducted in the Main Zoom Room featuring the Rector of Universitas Negeri Makassar and 5 international plenary speakers with interactive Q&A.</li>
  <li><strong>Parallel / Oral Presentations:</strong> Conducted across 20 Zoom Breakout Rooms, accommodating around 200 presenter papers (10–12 presenters per room, 7 minutes presentation + 3 minutes Q&A).</li>
</ul>

<h4>Conference Schedule & Timezone</h4>
<ul>
  <li><strong>Date:</strong> Saturday, 7 November 2026</li>
  <li><strong>Time:</strong> 09:00 – 15:10 Central Indonesia Time (CIT / UTC+8)</li>
  <li><strong>Official Language:</strong> English</li>
</ul>

<h4>Organizing Secretariat</h4>
<p><strong>Faculty of Economics and Business, Universitas Negeri Makassar (FEB UNM)</strong><br>
Jl. Raya Pendidikan, Makassar, South Sulawesi, Indonesia<br>
Official Email: <a href="mailto:icoman@unm.ac.id">icoman@unm.ac.id</a></p>',
                    'id' => '<h3>Konferensi Daring Penuh melalui Zoom Meeting</h3>
<p>Guna menjamin aksesibilitas partisipasi bagi peserta dan pemakalah dari berbagai belahan dunia tanpa kendala geografis, sekaligus menegaskan komitmen keberlanjutan dalam meminimalkan jejak karbon emisi perjalanan, <strong>ICOMAN 2026 diselenggarakan secara penuh secara daring (virtual conference) melalui platform Zoom Meeting</strong>.</p>

<h4>Format Sesi</h4>
<ul>
  <li><strong>Keynote Speech & Sesi Panel Pleno:</strong> Dilaksanakan di Main Zoom Room menghadirkan Rektor Universitas Negeri Makassar serta 5 pembicara panel internasional disertai sesi tanya jawab interaktif.</li>
  <li><strong>Presentasi Paralel / Oral:</strong> Dilaksanakan di 20 Zoom Breakout Rooms untuk menampung sekitar 200 naskah pemakalah (10–12 presenter per ruangan, 7 menit presentasi + 3 menit tanya jawab).</li>
</ul>

<h4>Jadwal & Zona Waktu</h4>
<ul>
  <li><strong>Hari & Tanggal:</strong> Sabtu, 7 November 2026</li>
  <li><strong>Waktu:</strong> 09:00 – 15:10 Waktu Indonesia Tengah (WITA / UTC+8)</li>
  <li><strong>Bahasa Resmi:</strong> Bahasa Inggris (English)</li>
</ul>

<h4>Sekretariat Penyelenggara</h4>
<p><strong>Fakultas Ekonomi dan Bisnis, Universitas Negeri Makassar (FEB UNM)</strong><br>
Jl. Raya Pendidikan, Makassar, Sulawesi Selatan, Indonesia<br>
Email Resmi: <a href="mailto:icoman@unm.ac.id">icoman@unm.ac.id</a></p>',
                ],
                'is_published' => true,
            ],
        );

        Page::updateOrCreate(
            ['slug' => 'call-for-papers'],
            [
                'edition_id' => $eid,
                'title' => [
                    'en' => 'Call for Papers — Scope & Sub-Themes',
                    'id' => 'Call for Papers — Ruang Lingkup & Sub-Tema',
                ],
                'content' => [
                    'en' => '<p>The International Conference of Management 2026 (ICOMAN 2026) invites scholars, researchers, graduate students, and industry professionals worldwide to submit papers in the following four strategic sub-themes:</p>

<h4>Sub-theme 1: Inclusive HR Resilience and Talent Sustainability in the Age of AI and Digital Innovation</h4>
<ul>
  <li>AI-based recruitment and selection that is inclusive and free of algorithmic bias</li>
  <li>Reskilling and upskilling strategies for workforce resilience</li>
  <li>Diversity, Equity, and Inclusion (DEI) in AI-based HR systems</li>
  <li>Employee well-being and mental health in AI-driven hybrid work environments</li>
  <li>AI ethics and algorithmic governance in HR decision-making</li>
  <li>The future of work: human–AI collaboration and the gig economy</li>
  <li>Transformational leadership to support organizational digital transformation</li>
  <li>Sustainable talent management across generations</li>
</ul>

<h4>Sub-theme 2: AI-Driven Experiential Marketing and Inclusive Sustainable Consumer Behavior</h4>
<ul>
  <li>Personalization and hyper-targeting in marketing through generative AI</li>
  <li>Marketing ethics and data privacy in AI-based campaigns</li>
  <li>Inclusive consumer behavior across demographic segments and accessibility</li>
  <li>Green consumerism and purchase intention for sustainable products</li>
  <li>Immersive technology (AR/VR/metaverse) in experiential marketing</li>
  <li>AI chatbots and conversational commerce in brand–consumer interaction</li>
  <li>Brand trust and authenticity in the era of AI-generated content</li>
  <li>AI-based customer value co-creation strategies</li>
</ul>

<h4>Sub-theme 3: Financial Inclusion, Green Investment, and Risk Management in the AI Era</h4>
<ul>
  <li>AI-based credit scoring for the financial inclusion of underserved groups</li>
  <li>Green finance, ESG investment, and sustainable portfolio management</li>
  <li>Fintech innovation and digital banking transformation</li>
  <li>AI-based risk and fraud detection</li>
  <li>Islamic finance and sustainable inclusive finance models</li>
  <li>Climate risk and financial system resilience</li>
  <li>Regulatory Technology (RegTech) and the strengthening of financial governance</li>
  <li>Digital financial literacy for MSMEs and marginalized groups</li>
</ul>

<h4>Sub-theme 4: Strategic Agility and Inclusive Business Models for Sustainable Corporate Resilience</h4>
<ul>
  <li>Dynamic capabilities and organizational agility in VUCA/BANI environments</li>
  <li>Inclusive business models for MSMEs and social entrepreneurship</li>
  <li>AI-based supply chain resilience</li>
  <li>Corporate sustainability strategy and triple bottom line performance (People, Planet, Profit)</li>
  <li>Digital transformation and business model innovation</li>
  <li>Stakeholder governance and corporate social responsibility (CSR)</li>
  <li>Crisis management and strategic foresight in facing disruption</li>
  <li>Strategic leadership for long-term corporate resilience</li>
</ul>',
                    'id' => '<p>International Conference of Management 2026 (ICOMAN 2026) mengundang akademisi, peneliti, mahasiswa pascasarjana, dan profesional industri dari seluruh dunia untuk mengirimkan karya ilmiah pada empat sub-tema strategis berikut:</p>

<h4>Sub-tema 1: Ketahanan SDM Inklusif dan Keberlanjutan Talenta di Era AI dan Inovasi Digital</h4>
<ul>
  <li>Rekrutmen dan seleksi berbasis AI yang inklusif dan bebas bias algoritmik</li>
  <li>Strategi reskilling dan upskilling untuk ketahanan tenaga kerja</li>
  <li>Keberagaman, Kesetaraan, dan Inklusi (DEI) dalam sistem SDM berbasis AI</li>
  <li>Kesejahteraan dan kesehatan mental karyawan di lingkungan kerja hybrid berbasis AI</li>
  <li>Etika AI dan tata kelola algoritmik dalam pengambilan keputusan SDM</li>
  <li>Masa depan dunia kerja: kolaborasi manusia–AI dan gig economy</li>
  <li>Kepemimpinan transformasional untuk mendukung transformasi digital organisasi</li>
  <li>Manajemen talenta berkelanjutan lintas generasi</li>
</ul>

<h4>Sub-tema 2: Pemasaran Berbasis Pengalaman Berbasis AI dan Perilaku Konsumen Berkelanjutan yang Inklusif</h4>
<ul>
  <li>Personalisasi dan hyper-targeting dalam pemasaran melalui generative AI</li>
  <li>Etika pemasaran dan privasi data dalam kampanye berbasis AI</li>
  <li>Perilaku konsumen inklusif lintas segmen demografis dan aksesibilitas</li>
  <li>Green consumerism dan niat beli untuk produk berkelanjutan</li>
  <li>Teknologi imersif (AR/VR/metaverse) dalam experiential marketing</li>
  <li>AI chatbot dan conversational commerce dalam interaksi merek–konsumen</li>
  <li>Kepercayaan merek dan autentisitas di era konten buatan AI</li>
  <li>Strategi co-creation nilai pelanggan berbasis AI</li>
</ul>

<h4>Sub-tema 3: Inklusi Keuangan, Investasi Hijau, dan Manajemen Risiko di Era AI</h4>
<ul>
  <li>Credit scoring berbasis AI untuk inklusi keuangan kelompok underserved</li>
  <li>Green finance, investasi ESG, dan manajemen portofolio berkelanjutan</li>
  <li>Inovasi fintech dan transformasi perbankan digital</li>
  <li>Deteksi risiko dan fraud berbasis AI</li>
  <li>Keuangan Islam dan model keuangan inklusif berkelanjutan</li>
  <li>Risiko iklim dan ketahanan sistem keuangan</li>
  <li>Regulatory Technology (RegTech) dan penguatan tata kelola keuangan</li>
  <li>Literasi keuangan digital untuk UMKM dan kelompok marginal</li>
</ul>

<h4>Sub-tema 4: Agilitas Strategis dan Model Bisnis Inklusif untuk Ketahanan Korporasi Berkelanjutan</h4>
<ul>
  <li>Kapabilitas dinamis dan kelincahan organisasi di lingkungan VUCA/BANI</li>
  <li>Model bisnis inklusif untuk UMKM dan kewirausahaan sosial</li>
  <li>Ketahanan rantai pasok berbasis AI</li>
  <li>Strategi keberlanjutan korporasi dan kinerja triple bottom line (People, Planet, Profit)</li>
  <li>Transformasi digital dan inovasi model bisnis</li>
  <li>Tata kelola pemangku kepentingan dan Corporate Social Responsibility (CSR)</li>
  <li>Manajemen krisis dan strategic foresight dalam menghadapi disrupsi</li>
  <li>Kepemimpinan strategis untuk ketahanan korporasi jangka panjang</li>
</ul>',
                ],
                'is_published' => true,
            ],
        );

        Page::updateOrCreate(
            ['slug' => 'publication'],
            [
                'edition_id' => $eid,
                'title' => [
                    'en' => 'Publication & Scientific Outputs',
                    'id' => 'Publikasi & Luaran Ilmiah',
                ],
                'content' => [
                    'en' => '<h3>Publication Opportunities & Scientific Outputs</h3>
<p>All presented papers at ICOMAN 2026 will undergo rigorous peer review. Selected high-quality papers will be eligible for various publication outputs and recognitions:</p>

<ul>
  <li><strong>Special Issue in Reputable Partner Journals:</strong> Papers of the highest quality will be given the opportunity to be recommended for publication in special issues of reputable partner journals.</li>
  <li><strong>Policy Brief:</strong> A synthesis of panel discussions and research findings compiled into evidence-based policy briefs distributed to key stakeholders.</li>
  <li><strong>Best Paper Award:</strong> Prestigious recognition and awards for outstanding papers with the most original and significant scientific contributions in each of the 4 sub-themes.</li>
  <li><strong>International Certificates:</strong> Certificates of participation and/or oral presentation for all registered authors and attendees.</li>
  <li><strong>Institutional Research Collaborations:</strong> Opportunities to establish cross-border research networks and formalize partnerships (MoU/MoA).</li>
</ul>',
                    'id' => '<h3>Peluang Publikasi & Luaran Ilmiah</h3>
<p>Seluruh paper yang dipresentasikan di ICOMAN 2026 akan melalui proses peer-review yang ketat. Paper terpilih berkesempatan mendapatkan luaran publikasi dan apresiasi ilmiah:</p>

<ul>
  <li><strong>Edisi Khusus Jurnal Mitra Bereputasi:</strong> Paper berkualitas terbaik berkesempatan direkomendasikan untuk terbit pada Special Issue jurnal mitra terakreditasi dan bereputasi.</li>
  <li><strong>Policy Brief:</strong> Sintesis hasil diskusi panel dan riset yang disusun sebagai ringkasan kebijakan strategis bagi para pembuat kebijakan dan pelaku bisnis.</li>
  <li><strong>Best Paper Award:</strong> Apresiasi dan penghargaan naskah terbaik dengan kontribusi ilmiah orisinal tertinggi pada masing-masing dari 4 sub-tema.</li>
  <li><strong>Sertifikat Internasional:</strong> Sertifikat kepesertaan dan/atau presentasi berskala internasional bagi seluruh peserta dan pemakalah terdaftar.</li>
  <li><strong>Kolaborasi Riset Institusional:</strong> Kesempatan memperluas jejaring riset internasional serta inisiasi kerja sama formal (MoU/MoA).</li>
</ul>',
                ],
                'is_published' => true,
            ],
        );

        // 6. FAQs (Tailored to TOR)
        Faq::where('edition_id', $eid)->delete();
        $faqs = [
            [
                'q' => [
                    'en' => 'What is the format of ICOMAN 2026?',
                    'id' => 'Apa format pelaksanaan konferensi ICOMAN 2026?',
                ],
                'a' => [
                    'en' => 'ICOMAN 2026 is conducted fully online (virtual conference) via the Zoom Meeting platform to allow seamless global participation while minimizing carbon emissions.',
                    'id' => 'ICOMAN 2026 diselenggarakan secara penuh secara daring (virtual conference) melalui platform Zoom Meeting untuk memudahkan partisipasi global dan menekan jejak karbon.',
                ],
            ],
            [
                'q' => [
                    'en' => 'What is the official language of the conference?',
                    'id' => 'Apa bahasa resmi yang digunakan dalam konferensi?',
                ],
                'a' => [
                    'en' => 'English is the official language for all paper submissions, oral presentations, and keynote panel sessions.',
                    'id' => 'Bahasa Inggris (English) adalah bahasa resmi untuk seluruh penulisan paper, presentasi oral, serta sesi pleno pembicara utama.',
                ],
            ],
            [
                'q' => [
                    'en' => 'How are the parallel presentation sessions organized?',
                    'id' => 'Bagaimana sesi presentasi paralel dilaksanakan?',
                ],
                'a' => [
                    'en' => 'Parallel sessions will be held across 20 Zoom Breakout Rooms. Each presenter is allotted 7 minutes for presentation followed by 3 minutes for Q&A and discussant feedback.',
                    'id' => 'Sesi paralel dilaksanakan di 20 Zoom Breakout Rooms. Setiap pemakalah dialokasikan 7 menit untuk presentasi dan 3 menit untuk tanya jawab serta masukan dari reviewer.',
                ],
            ],
            [
                'q' => [
                    'en' => 'What publication opportunities are available?',
                    'id' => 'Apa peluang publikasi yang tersedia untuk pemakalah?',
                ],
                'a' => [
                    'en' => 'Selected high-quality papers will be recommended for publication in special issues of reputable partner journals and policy briefs. All participants receive international certificates.',
                    'id' => 'Paper berkualitas terbaik berpeluang direkomendasikan pada edisi khusus jurnal mitra bereputasi dan policy brief. Seluruh peserta memperoleh sertifikat internasional.',
                ],
            ],
        ];
        foreach ($faqs as $i => $item) {
            Faq::create([
                'edition_id' => $eid,
                'question' => $item['q'],
                'answer' => $item['a'],
                'order' => $i + 1,
            ]);
        }

        // 7. Speakers & Committees from TOR (Speakers TBA)
        Speaker::where('edition_id', $eid)->delete();
        $speakers = [
            [
                'type' => 'keynote',
                'title_degree' => null,
                'name' => 'Keynote Speaker 1 (TBA)',
                'affiliation' => 'International Partner University',
                'country' => null,
                'topic' => [
                    'en' => 'Inclusive HR Resilience and Talent Sustainability in the Age of AI',
                    'id' => 'Ketahanan SDM Inklusif dan Keberlanjutan Talenta di Era AI',
                ],
                'bio' => 'To Be Announced (TBA)',
            ],
            [
                'type' => 'keynote',
                'title_degree' => null,
                'name' => 'Keynote Speaker 2 (TBA)',
                'affiliation' => 'International Partner University',
                'country' => null,
                'topic' => [
                    'en' => 'AI-Driven Experiential Marketing and Sustainable Consumer Behavior',
                    'id' => 'Pemasaran Berbasis Pengalaman Berbasis AI dan Perilaku Konsumen Berkelanjutan',
                ],
                'bio' => 'To Be Announced (TBA)',
            ],
            [
                'type' => 'keynote',
                'title_degree' => null,
                'name' => 'Keynote Speaker 3 (TBA)',
                'affiliation' => 'International Partner University',
                'country' => null,
                'topic' => [
                    'en' => 'Financial Inclusion, Green Investment, and Risk Management in the AI Era',
                    'id' => 'Inklusi Keuangan, Investasi Hijau, dan Manajemen Risiko di Era AI',
                ],
                'bio' => 'To Be Announced (TBA)',
            ],
            [
                'type' => 'keynote',
                'title_degree' => null,
                'name' => 'Keynote Speaker 4 (TBA)',
                'affiliation' => 'International Partner University',
                'country' => null,
                'topic' => [
                    'en' => 'Strategic Agility and Inclusive Business Models for Corporate Resilience',
                    'id' => 'Agilitas Strategis dan Model Bisnis Inklusif untuk Ketahanan Korporasi',
                ],
                'bio' => 'To Be Announced (TBA)',
            ],
            [
                'type' => 'keynote',
                'title_degree' => null,
                'name' => 'Keynote Speaker 5 (TBA)',
                'affiliation' => 'International Partner University',
                'country' => null,
                'topic' => [
                    'en' => 'International Perspectives on Business Resilience and Inclusive Sustainable Development',
                    'id' => 'Perspektif Internasional tentang Ketahanan Bisnis dan Pembangunan Berkelanjutan Inklusif',
                ],
                'bio' => 'To Be Announced (TBA)',
            ],
        ];
        foreach ($speakers as $i => $sp) {
            Speaker::create([
                'edition_id' => $eid,
                'type' => $sp['type'],
                'title_degree' => $sp['title_degree'],
                'name' => $sp['name'],
                'affiliation' => $sp['affiliation'],
                'country' => $sp['country'],
                'topic' => $sp['topic'],
                'bio' => $sp['bio'],
                'order' => $i + 1,
            ]);
        }

        Committee::where('edition_id', $eid)->delete();
        $committees = [
            [
                'category' => 'steering',
                'name' => 'Rector of Universitas Negeri Makassar',
                'role_title' => [
                    'en' => 'Advisory Board / Keynote Speaker',
                    'id' => 'Dewan Pembina / Keynote Speaker',
                ],
                'affiliation' => 'Universitas Negeri Makassar',
            ],
            [
                'category' => 'steering',
                'name' => 'Dean of Faculty of Economics & Business',
                'role_title' => [
                    'en' => 'Steering Committee / Welcome Remarks',
                    'id' => 'Dewan Pengarah / Sambutan Pembuka',
                ],
                'affiliation' => 'Universitas Negeri Makassar (FEB UNM)',
            ],
            [
                'category' => 'steering',
                'name' => 'Head of Management Department',
                'role_title' => [
                    'en' => 'Steering Committee',
                    'id' => 'Dewan Pengarah',
                ],
                'affiliation' => 'FEB Universitas Negeri Makassar',
            ],
            [
                'category' => 'organizing',
                'name' => 'Achmad Ridha, S.M., M.M.',
                'role_title' => [
                    'en' => 'Organizing Committee Secretariat',
                    'id' => 'Sekretariat Panitia Pelaksana',
                ],
                'affiliation' => 'Universitas Negeri Makassar',
            ],
        ];
        foreach ($committees as $i => $c) {
            Committee::create([
                'edition_id' => $eid,
                'category' => $c['category'],
                'name' => $c['name'],
                'role_title' => $c['role_title'],
                'affiliation' => $c['affiliation'],
                'order' => $i + 1,
            ]);
        }

        // 8. Registration Fees — per audience (presenter/participant) × kategori
        //    (Mahasiswa S1 / Dosen-Umum / International).
        $fees = [
            [
                'category' => ['en' => 'Presenter — Undergraduate (S1)', 'id' => 'Pemakalah — Mahasiswa S1'],
                'audience' => 'presenter', 'registrant_category' => 'student_s1',
                'notes' => 'Undergraduate (S1) presenter rate. Includes presentation slot, certificate, and review feedback.',
            ],
            [
                'category' => ['en' => 'Presenter — Lecturer/General', 'id' => 'Pemakalah — Dosen/Umum'],
                'audience' => 'presenter', 'registrant_category' => 'general',
                'notes' => 'Includes presentation slot (Zoom Breakout Room), international certificate, policy brief, and review feedback.',
            ],
            [
                'category' => ['en' => 'Presenter — International', 'id' => 'Pemakalah — Internasional'],
                'audience' => 'presenter', 'registrant_category' => 'international',
                'notes' => 'International presenter rate. Includes virtual presentation slot, international certificate, and journal recommendation eligibility.',
            ],
            [
                'category' => ['en' => 'Attendee — Undergraduate (S1)', 'id' => 'Peserta — Mahasiswa S1'],
                'audience' => 'participant', 'registrant_category' => 'student_s1',
                'notes' => 'Undergraduate (S1) attendee rate. Access to Main Zoom Room and International E-Certificate.',
            ],
            [
                'category' => ['en' => 'Attendee — Lecturer/General', 'id' => 'Peserta — Dosen/Umum'],
                'audience' => 'participant', 'registrant_category' => 'general',
                'notes' => 'Access to Main Zoom Room (Keynote & Plenary Panel Sessions) and International E-Certificate.',
            ],
            [
                'category' => ['en' => 'Attendee — International', 'id' => 'Peserta — Internasional'],
                'audience' => 'participant', 'registrant_category' => 'international',
                'notes' => 'International attendee rate. Access to Main Zoom Room and International E-Certificate.',
            ],
        ];
        $existingFees = RegistrationFee::where('edition_id', $eid)->orderBy('id')->get();
        foreach ($fees as $i => $fee) {
            $payload = [
                'category' => $fee['category'],
                'audience' => $fee['audience'],
                'registrant_category' => $fee['registrant_category'],
                'price_regular' => $fee['price_regular'],
                'currency' => $fee['currency'],
                'notes' => $fee['notes'],
                'order' => $i + 1,
            ];

            if (isset($existingFees[$i])) {
                $existingFees[$i]->update($payload);
            } else {
                RegistrationFee::create(['edition_id' => $eid] + $payload);
            }
        }
    }
}
