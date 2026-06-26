<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://player.vimeo.com https://www.youtube.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-src 'self' https://mtravels.org https://www.mtravels.org https://player.vimeo.com https://www.youtube.com;");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

$allowed_roles = ['admin', 'finance', 'sales', 'umrah', 'staff', 'tenant_super_admin'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../includes/header.php';

$user_role = $_SESSION['role'];

$tutorials = [];
try {
    require_once '../includes/db.php';
    $stmt = $pdo->prepare("SELECT * FROM tutorials WHERE status = 1 ORDER BY sort_order ASC, id ASC");
    $stmt->execute();
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all as $t) {
        $roles = json_decode($t['roles'], true);
        if (!is_array($roles)) {
            $roles = ['all'];
        }
        if (in_array('all', $roles) || in_array($user_role, $roles)) {
            $tutorials[] = $t;
        }
    }
} catch (PDOException $e) {
}

$selected_category = isset($_GET['category']) ? htmlspecialchars($_GET['category']) : 'all';
$filtered_tutorials = $selected_category === 'all' ? $tutorials : array_filter($tutorials, function($t) use ($selected_category) {
    return ($t['category'] ?? '') === $selected_category;
});

$categories = array_values(array_unique(array_filter(array_column($tutorials, 'category'))));
sort($categories);
?>

<style>
    .tutorial-container { padding: 20px; }
    .tutorial-header { margin-bottom: 30px; }
    .tutorial-title { font-size: 2.5rem; font-weight: 600; margin-bottom: 10px; color: #2c3e50; }
    .tutorial-subtitle { font-size: 1.1rem; color: #7f8c8d; margin-bottom: 0; }
    .category-filter { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
    .category-btn { padding: 8px 18px; border: 2px solid #e0e0e0; background-color: #fff; color: #333; border-radius: 25px; cursor: pointer; transition: all 0.3s ease; font-size: 0.95rem; font-weight: 500; }
    .category-btn:hover { border-color: #4680ff; color: #4680ff; }
    .category-btn.active { background-color: #4680ff; color: white; border-color: #4680ff; }
    .tutorials-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; margin-bottom: 40px; }
    .tutorial-card { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s ease; cursor: pointer; height: 100%; display: flex; flex-direction: column; }
    .tutorial-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.15); transform: translateY(-5px); }
    .tutorial-thumbnail { width: 100%; height: 180px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; position: relative; overflow: hidden; }
    .tutorial-thumbnail::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.1); }
    .tutorial-thumbnail.youtube-bg { background: linear-gradient(135deg, #e53935 0%, #c62828 100%); }
    .tutorial-thumbnail.vimeo-bg { background: linear-gradient(135deg, #1ab7ea 0%, #0d47a1 100%); }
    .play-icon { position: relative; z-index: 2; width: 60px; height: 60px; background: rgba(255,255,255,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; transition: all 0.3s ease; }
    .tutorial-card:hover .play-icon { background: rgba(255,255,255,0.5); transform: scale(1.1); }
    .tutorial-content { padding: 20px; flex: 1; display: flex; flex-direction: column; }
    .tutorial-category { display: inline-block; background-color: #f0f0f0; color: #4680ff; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-bottom: 10px; width: fit-content; }
    .tutorial-card-title { font-size: 1.1rem; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
    .tutorial-card-description { font-size: 0.9rem; color: #7f8c8d; margin-bottom: 12px; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .tutorial-meta { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #95a5a6; border-top: 1px solid #ecf0f1; padding-top: 12px; margin-top: auto; }
    .tutorial-duration { display: flex; align-items: center; gap: 5px; }
    .tutorial-level { padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 500; }
    .level-beginner { background-color: #d4edda; color: #155724; }
    .level-intermediate { background-color: #fff3cd; color: #856404; }
    .level-advanced { background-color: #f8d7da; color: #721c24; }
    .video-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); align-items: center; justify-content: center; }
    .video-modal.show { display: flex; }
    .video-modal-content { position: relative; background-color: #000; width: 90%; max-width: 900px; border-radius: 8px; overflow: hidden; }
    .video-modal-close { position: absolute; top: 15px; right: 20px; color: white; font-size: 28px; font-weight: bold; cursor: pointer; z-index: 10; background: rgba(0,0,0,0.5); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; }
    .video-modal-close:hover { background: rgba(0,0,0,0.8); }
    .video-container { position: relative; width: 100%; padding-bottom: 56.25%; height: 0; overflow: hidden; }
    .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
    .no-tutorials { text-align: center; padding: 60px 20px; color: #7f8c8d; }
    .no-tutorials-icon { font-size: 4rem; margin-bottom: 20px; }
    .no-tutorials-title { font-size: 1.5rem; color: #2c3e50; margin-bottom: 10px; }
    .search-box { margin-bottom: 30px; }
    .search-box input { width: 100%; padding: 12px 20px; border: 2px solid #e0e0e0; border-radius: 25px; font-size: 1rem; transition: all 0.3s ease; }
    .search-box input:focus { outline: none; border-color: #4680ff; box-shadow: 0 0 0 3px rgba(70, 128, 255, 0.1); }
    .video-chapters { background: #1a1a2e; padding: 16px 20px; border-top: 1px solid #333; }
    .video-chapters-title { color: #aaa; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
    .video-chapters-list { display: flex; flex-wrap: wrap; gap: 8px; }
    .chapter-item { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.08); border-radius: 6px; padding: 6px 12px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent; }
    .chapter-item:hover { background: rgba(70, 128, 255, 0.2); border-color: #4680ff; }
    .chapter-time { font-size: 0.8rem; font-weight: 700; color: #4680ff; font-family: monospace; white-space: nowrap; }
    .chapter-label { font-size: 0.85rem; color: #e0e0e0; }
    .chapter-item:hover .chapter-label { color: #fff; }
    @media (max-width: 768px) {
        .tutorials-grid { grid-template-columns: 1fr; }
        .category-filter { justify-content: center; }
        .tutorial-title { font-size: 1.8rem; }
    }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper tutorial-container">

                        <div class="tutorial-header">
                            <h1 class="tutorial-title"><i class="feather icon-play-circle mr-2"></i>Tutorials</h1>
                            <p class="tutorial-subtitle">Learn how to use MTravels Admin Dashboard</p>
                        </div>

                        <div class="search-box">
                            <input type="text" id="tutorialSearch" placeholder="Search tutorials..." onkeyup="filterTutorials()">
                        </div>

                        <div class="category-filter">
                            <button class="category-btn <?= $selected_category === 'all' ? 'active' : '' ?>" onclick="filterByCategory('all')">
                                <i class="feather icon-grid mr-2"></i>All Tutorials
                            </button>
                            <?php foreach ($categories as $category): ?>
                                <button class="category-btn <?= $selected_category === $category ? 'active' : '' ?>" onclick="filterByCategory('<?= htmlspecialchars($category) ?>')">
                                    <i class="feather icon-bookmark mr-2"></i><?= ucfirst(htmlspecialchars($category)) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="tutorials-grid" id="tutorialsGrid">
                            <?php if (count($filtered_tutorials) > 0): ?>
                                <?php foreach ($filtered_tutorials as $tutorial): ?>
                                    <div class="tutorial-card" onclick="playVideo(<?= htmlspecialchars(json_encode($tutorial)) ?>)" data-title="<?= htmlspecialchars($tutorial['title']) ?>" data-category="<?= htmlspecialchars($tutorial['category'] ?? '') ?>">
                                        <div class="tutorial-thumbnail" style="background: <?php if (($tutorial['video_type'] ?? 'vimeo') === 'youtube' && !empty($tutorial['video_id'])): ?>url('https://img.youtube.com/vi/<?= htmlspecialchars($tutorial['video_id']) ?>/hqdefault.jpg') center/cover<?php else: ?>linear-gradient(135deg, #667eea 0%, #764ba2 100%)<?php endif; ?>;" <?= (($tutorial['video_type'] ?? 'vimeo') === 'vimeo' && !empty($tutorial['video_id'])) ? 'data-vimeo="' . htmlspecialchars($tutorial['video_id']) . '"' : '' ?>>
                                            <div class="play-icon"><i class="<?= ($tutorial['video_type'] ?? 'vimeo') === 'youtube' ? 'fab fa-youtube' : 'fas fa-play' ?>"></i></div>
                                        </div>
                                        <div class="tutorial-content">
                                            <span class="tutorial-category"><?= htmlspecialchars($tutorial['category'] ?? '') ?></span>
                                            <h3 class="tutorial-card-title"><?= htmlspecialchars($tutorial['title']) ?></h3>
                                            <p class="tutorial-card-description"><?= htmlspecialchars($tutorial['description'] ?? '') ?></p>
                                            <div class="tutorial-meta">
                                                <div class="tutorial-duration"><i class="feather icon-clock"></i><span><?= htmlspecialchars($tutorial['duration'] ?? '5:00') ?></span></div>
                                                <div class="tutorial-level level-<?= strtolower(htmlspecialchars($tutorial['level'] ?? 'Beginner')) ?>"><?= htmlspecialchars($tutorial['level'] ?? 'Beginner') ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="grid-column: 1 / -1;">
                                    <div class="no-tutorials">
                                        <div class="no-tutorials-icon"><i class="feather icon-inbox"></i></div>
                                        <div class="no-tutorials-title">No tutorials found</div>
                                        <p>No tutorials are available for your role yet. Please check back later.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="videoModal" class="video-modal">
    <div class="video-modal-content">
        <span class="video-modal-close" onclick="closeVideo()">&times;</span>
        <div class="video-container">
            <iframe id="videoPlayer" src="" allow="autoplay; fullscreen; picture-in-picture"></iframe>
        </div>
        <div id="chaptersSection" class="video-chapters" style="display:none;">
            <div class="video-chapters-title"><i class="feather icon-list mr-2"></i>Chapters</div>
            <div id="chaptersList" class="video-chapters-list"></div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
    const tutorials = <?= json_encode($tutorials) ?>;

    let currentVideoType = null;
    let currentChapters = [];
    let ytPlayer = null;
    let vimeoPlayer = null;
    let ytApiLoaded = false;
    let vimeoApiLoaded = false;

    function getVideoUrl(tutorial) {
        const type = tutorial.video_type || 'vimeo';
        const id = tutorial.video_id || '';
        if (!id) return '';
        if (type === 'youtube') {
            return 'https://www.youtube.com/embed/' + id + '?autoplay=1&rel=0&enablejsapi=1';
        }
        return 'https://player.vimeo.com/video/' + id + '?autoplay=1';
    }

    function playVideo(tutorial) {
        const modal = document.getElementById('videoModal');
        const iframe = document.getElementById('videoPlayer');

        currentVideoType = tutorial.video_type || 'vimeo';
        currentChapters = [];
        try { currentChapters = JSON.parse(tutorial.chapters || '[]'); } catch(e) {}
        ytPlayer = null;
        vimeoPlayer = null;

        const url = getVideoUrl(tutorial);
        iframe.src = url;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';

        renderChapters();
        loadPlayerAPI();
    }

    function renderChapters() {
        const section = document.getElementById('chaptersSection');
        const list = document.getElementById('chaptersList');
        if (!currentChapters.length) {
            section.style.display = 'none';
            return;
        }
        section.style.display = 'block';
        list.innerHTML = currentChapters.map(function(ch, i) {
            return '<div class="chapter-item" onclick="seekToChapter(' + i + ')">'
                + '<span class="chapter-time">' + esc(ch.time) + '</span>'
                + '<span class="chapter-label">' + esc(ch.label) + '</span>'
                + '</div>';
        }).join('');
    }

    function seekToChapter(index) {
        const ch = currentChapters[index];
        if (!ch) return;
        const seconds = ch.seconds || 0;
        if (currentVideoType === 'youtube' && ytPlayer && typeof ytPlayer.seekTo === 'function') {
            ytPlayer.seekTo(seconds, true);
        } else if (currentVideoType === 'vimeo' && vimeoPlayer && typeof vimeoPlayer.setCurrentTime === 'function') {
            vimeoPlayer.setCurrentTime(seconds).catch(function() {});
        }
    }

    function loadPlayerAPI() {
        if (currentVideoType === 'youtube') {
            if (!ytApiLoaded) {
                ytApiLoaded = true;
                var tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                var first = document.getElementsByTagName('script')[0];
                first.parentNode.insertBefore(tag, first);
            }
            // Wait for iframe to load then create player
            var iframe = document.getElementById('videoPlayer');
            var checkInterval = setInterval(function() {
                if (typeof YT !== 'undefined' && YT.loaded) {
                    clearInterval(checkInterval);
                    if (!ytPlayer) {
                        try {
                            ytPlayer = new YT.Player('videoPlayer', {
                                events: {
                                    'onReady': function() {
                                        if (currentChapters.length && currentChapters[0].seconds) {
                                            // If first chapter time > 0, optionally seek to start
                                        }
                                    }
                                }
                            });
                        } catch(e) {}
                    }
                }
            }, 500);
        } else if (currentVideoType === 'vimeo') {
            if (!vimeoApiLoaded) {
                vimeoApiLoaded = true;
                var tag = document.createElement('script');
                tag.src = 'https://player.vimeo.com/api/player.js';
                var first = document.getElementsByTagName('script')[0];
                first.parentNode.insertBefore(tag, first);
            }
            var iframe = document.getElementById('videoPlayer');
            var checkInterval = setInterval(function() {
                if (typeof Vimeo !== 'undefined' && Vimeo.Player) {
                    clearInterval(checkInterval);
                    if (!vimeoPlayer) {
                        try {
                            vimeoPlayer = new Vimeo.Player(iframe);
                        } catch(e) {}
                    }
                }
            }, 500);
        }
    }

    function closeVideo() {
        const modal = document.getElementById('videoModal');
        const iframe = document.getElementById('videoPlayer');
        iframe.src = '';
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
        currentChapters = [];
        ytPlayer = null;
        vimeoPlayer = null;
    }

    function filterByCategory(category) {
        const filtered = category === 'all' ? tutorials : tutorials.filter(t => t.category === category);
        document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
        event.target.closest('.category-btn').classList.add('active');
        displayTutorials(filtered);
    }

    function filterTutorials() {
        const searchInput = document.getElementById('tutorialSearch').value.toLowerCase();
        const filtered = tutorials.filter(tutorial =>
            (tutorial.title || '').toLowerCase().includes(searchInput) ||
            (tutorial.description || '').toLowerCase().includes(searchInput) ||
        (tutorial.category || '').toLowerCase().includes(searchInput)
        );
        displayTutorials(filtered);
    }

    function displayTutorials(tutorialsToDisplay) {
        const grid = document.getElementById('tutorialsGrid');
        if (tutorialsToDisplay.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1 / -1;"><div class="no-tutorials"><div class="no-tutorials-icon"><i class="feather icon-inbox"></i></div><div class="no-tutorials-title">No tutorials found</div><p>Try adjusting your search or filter criteria.</p></div></div>';
            return;
        }
        grid.innerHTML = tutorialsToDisplay.map(tutorial => {
            const type = tutorial.video_type || 'vimeo';
            const vid = tutorial.video_id || '';
            const thumbStyle = (type === 'youtube' && vid) ? 'background:url(https://img.youtube.com/vi/' + vid + '/hqdefault.jpg) center/cover' : 'background:linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
            const icon = type === 'youtube' ? 'fab fa-youtube' : 'fas fa-play';
            const title = esc(tutorial.title);
            const desc = esc(tutorial.description || '');
            const cat = esc(tutorial.category || '');
            const dur = esc(tutorial.duration || '5:00');
            const lvl = esc(tutorial.level || 'Beginner');
            const data = esc(JSON.stringify(tutorial));
            const vimeoAttr = (type === 'vimeo' && vid) ? ' data-vimeo="' + vid + '"' : '';
            return '<div class="tutorial-card" onclick="playVideo(' + data + ')" data-title="' + title + '" data-category="' + cat + '">'
                + '<div class="tutorial-thumbnail" style="' + thumbStyle + '"' + vimeoAttr + '><div class="play-icon"><i class="' + icon + '"></i></div></div>'
                + '<div class="tutorial-content">'
                + '<span class="tutorial-category">' + cat + '</span>'
                + '<h3 class="tutorial-card-title">' + title + '</h3>'
                + '<p class="tutorial-card-description">' + desc + '</p>'
                + '<div class="tutorial-meta">'
                + '<div class="tutorial-duration"><i class="feather icon-clock"></i><span>' + dur + '</span></div>'
                + '<div class="tutorial-level level-' + lvl.toLowerCase() + '">' + lvl + '</div>'
                + '</div></div></div>';
        }).join('');
        document.querySelectorAll('.tutorial-thumbnail[data-vimeo]').forEach(function(el) {
            loadVimeoThumb(el, el.getAttribute('data-vimeo'));
        });
    }

    function loadVimeoThumb(el, videoId) {
        fetch('../api/tutorials/thumb.php?video_type=vimeo&video_id=' + encodeURIComponent(videoId))
            .then(r => r.json())
            .then(data => {
                if (data.success && data.url) {
                    el.style.background = 'url(' + data.url + ') center/cover';
                }
            });
    }

    function esc(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.tutorial-thumbnail[data-vimeo]').forEach(function(el) {
            loadVimeoThumb(el, el.getAttribute('data-vimeo'));
        });
    });

    window.onclick = function(event) {
        const modal = document.getElementById('videoModal');
        if (event.target === modal) {
            closeVideo();
        }
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeVideo();
        }
    });

    // Auto-play tutorial if ?play=ID is in URL
    (function() {
        var params = new URLSearchParams(window.location.search);
        var playId = params.get('play');
        if (playId) {
            var tutorial = tutorials.find(function(t) { return t.id == playId; });
            if (tutorial) {
                setTimeout(function() { playVideo(tutorial); }, 500);
            }
        }
    })();
</script>

</body>
</html>
