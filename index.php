<?php
// ============================================================
//  MovieBook — Single File App
// ============================================================
include("config.php");

$page   = $_GET['page'] ?? 'list';
$genres = ["Action","Comedy","Drama","Horror","Romance","SciFi"];

// -------- Helper: average rating --------
function avgRating($conn, $id) {
    $s = $conn->prepare("SELECT AVG(rating) as avg FROM ratings WHERE movie_id=?");
    $s->bindValue(1, $id);
    $r = $s->execute()->fetchArray(SQLITE3_ASSOC);
    return ($r && $r['avg']) ? round($r['avg'], 1) . " / 5" : "N/A";
}

// -------- Helper: render a movie card --------
function renderCard($row, $conn) {
    $id     = $row['movie_id'];
    $rating = avgRating($conn, $id);
    $img    = $row['poster'] ? $row['poster'] : "https://placehold.co/720x405/1a1a1a/555?text=No+Poster";
    $link   = $row['link']   ? $row['link']   : "#";
    $title  = htmlspecialchars($row['title']);
    $genre  = htmlspecialchars($row['genre']);
    $year   = htmlspecialchars($row['release_year']);
    echo "
    <div class='card'>
        <div class='card-img'>
            <img src='$img' alt='$title'>
        </div>
        <div class='card-info'>
            <h3>$title</h3>
            <div class='card-meta'>
                <span class='badge'>$genre</span>
                <span class='card-year'>$year</span>
                <span class='card-rating'>&#9733; $rating</span>
            </div>
            <div class='actions'>
                <a href='$link' target='_blank' class='btn'>Watch</a>
                <a href='index.php?page=edit&id=$id' class='btn'>Edit</a>
                <a href='index.php?action=delete&id=$id' onclick=\"return confirm('Delete this movie?')\" class='btn btn-delete'>Delete</a>
                <a href='index.php?page=rate&id=$id' class='btn'>Rate</a>
            </div>
        </div>
    </div>";
}

// ============================================================
//  ACTIONS (POST / DELETE — run before any HTML output)
// ============================================================

// --- Delete ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $conn->exec("DELETE FROM movies WHERE movie_id=" . (int)$_GET['id']);
    header("Location: index.php"); exit();
}

// --- Add movie ---
if ($page === 'add' && isset($_POST['submit'])) {
    $posterPath = "";
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
        $name = time() . "_" . basename($_FILES['poster']['name']);
        move_uploaded_file($_FILES['poster']['tmp_name'], "uploads/" . $name);
        $posterPath = "uploads/" . $name;
    }
    $s = $conn->prepare("INSERT INTO movies(title,genre,release_year,poster,link) VALUES(?,?,?,?,?)");
    $s->bindValue(1, $_POST['title']);
    $s->bindValue(2, $_POST['genre']);
    $s->bindValue(3, $_POST['year']);
    $s->bindValue(4, $posterPath);
    $s->bindValue(5, $_POST['link']);
    $s->execute();
    header("Location: index.php"); exit();
}

// --- Edit movie ---
if ($page === 'edit' && isset($_POST['update']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    // keep existing poster unless a new one is uploaded
    $cur = $conn->prepare("SELECT poster FROM movies WHERE movie_id=?");
    $cur->bindValue(1, $id);
    $posterPath = $cur->execute()->fetchArray(SQLITE3_ASSOC)['poster'];

    if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
        $name = time() . "_" . basename($_FILES['poster']['name']);
        move_uploaded_file($_FILES['poster']['tmp_name'], "uploads/" . $name);
        $posterPath = "uploads/" . $name;
    }
    $s = $conn->prepare("UPDATE movies SET title=?,genre=?,release_year=?,poster=?,link=? WHERE movie_id=?");
    $s->bindValue(1, $_POST['title']);
    $s->bindValue(2, $_POST['genre']);
    $s->bindValue(3, $_POST['year']);
    $s->bindValue(4, $posterPath);
    $s->bindValue(5, $_POST['link']);
    $s->bindValue(6, $id);
    $s->execute();
    header("Location: index.php"); exit();
}

// --- Rate movie ---
if ($page === 'rate' && isset($_POST['rating']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $s = $conn->prepare("INSERT INTO ratings(movie_id, rating) VALUES(?,?)");
    $s->bindValue(1, (int)$_GET['id']);
    $s->bindValue(2, (int)$_POST['rating']);
    $s->execute();
    header("Location: index.php"); exit();
}

// ============================================================
//  HTML OUTPUT
// ============================================================
$navPages = [
    'list'   => 'All Movies',
    'add'    => '+ Add',
    'search' => 'Search',
    'filter' => 'Filter',
    'sort'   => 'Top Rated',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MovieBook</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ===== BACKGROUND EFFECTS ===== -->
<div id="bg-canvas">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
    <div class="blob blob-4"></div>
</div>
<canvas id="particles"></canvas>
<div id="cursor-glow"></div>
<div id="cursor-dot"></div>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <a href="index.php" class="navbar-brand">MovieBook</a>
    <div class="navbar-links">
        <?php foreach($navPages as $p => $label): ?>
            <a href="index.php?page=<?= $p ?>" <?= $page === $p ? 'class="active"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</nav>

<?php
// ============================================================
//  PAGE: LIST (default)
// ============================================================
if ($page === 'list'):
    $res    = $conn->query("SELECT * FROM movies");
    $movies = [];
    if ($res) while($r = $res->fetchArray(SQLITE3_ASSOC)) $movies[] = $r;
?>
<div class="page-header">
    <h1>All Movies</h1>
    <p>Your complete movie collection</p>
</div>
<div class="container">
    <?php if (empty($movies)): ?>
        <div class="empty-state"><p>No movies yet. <a href="index.php?page=add" style="color:#fff;">Add your first one!</a></p></div>
    <?php else: foreach($movies as $row) renderCard($row, $conn); endif; ?>
</div>

<?php
// ============================================================
//  PAGE: SEARCH
// ============================================================
elseif ($page === 'search'):
    $q = $_GET['q'] ?? '';
?>
<div class="page-header">
    <h1>Search</h1>
    <p>Find movies by title</p>
</div>
<form method="GET" class="search-bar">
    <input type="hidden" name="page" value="search">
    <input type="text" name="q" placeholder="Search movies..." value="<?= htmlspecialchars($q) ?>">
    <button type="submit">Search</button>
</form>
<div class="container">
<?php
    if ($q !== '') {
        $s = $conn->prepare("SELECT * FROM movies WHERE title LIKE ?");
        $s->bindValue(1, "%$q%");
        $res = $s->execute();
        $movies = [];
        if ($res) while($r = $res->fetchArray(SQLITE3_ASSOC)) $movies[] = $r;
        if (empty($movies))
            echo "<div class='empty-state'><p>No results for \"" . htmlspecialchars($q) . "\"</p></div>";
        else foreach($movies as $row) renderCard($row, $conn);
    }
?>
</div>

<?php
// ============================================================
//  PAGE: FILTER
// ============================================================
elseif ($page === 'filter'):
    $g = $_GET['genre'] ?? '';
?>
<div class="page-header">
    <h1>Filter</h1>
    <p>Browse movies by genre</p>
</div>
<form method="GET" class="search-bar">
    <input type="hidden" name="page" value="filter">
    <select name="genre">
        <option value="">All Genres</option>
        <?php foreach($genres as $genre): ?>
            <option <?= $g === $genre ? 'selected' : '' ?>><?= $genre ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filter</button>
</form>
<div class="container">
<?php
    if ($g !== '') {
        $s = $conn->prepare("SELECT * FROM movies WHERE genre=?");
        $s->bindValue(1, $g);
        $res = $s->execute();
        $movies = [];
        if ($res) while($r = $res->fetchArray(SQLITE3_ASSOC)) $movies[] = $r;
        if (empty($movies))
            echo "<div class='empty-state'><p>No movies found for genre: " . htmlspecialchars($g) . "</p></div>";
        else foreach($movies as $row) renderCard($row, $conn);
    }
?>
</div>

<?php
// ============================================================
//  PAGE: SORT (Top Rated)
// ============================================================
elseif ($page === 'sort'):
    $res = $conn->query("
        SELECT m.*, AVG(r.rating) as avg_rating
        FROM movies m LEFT JOIN ratings r ON m.movie_id = r.movie_id
        GROUP BY m.movie_id ORDER BY avg_rating DESC
    ");
    $movies = [];
    if ($res) while($r = $res->fetchArray(SQLITE3_ASSOC)) $movies[] = $r;
?>
<div class="page-header">
    <h1>Top Rated</h1>
    <p>Movies ranked by average user rating</p>
</div>
<div class="container">
    <?php if (empty($movies)): ?>
        <div class="empty-state"><p>No movies yet.</p></div>
    <?php else: foreach($movies as $row) renderCard($row, $conn); endif; ?>
</div>

<?php
// ============================================================
//  PAGE: ADD
// ============================================================
elseif ($page === 'add'):
?>
<div class="page-header">
    <h1>Add Movie</h1>
    <p>Add a new movie to your collection</p>
</div>
<div class="form-page">
    <div class="form-card">
        <form method="POST" enctype="multipart/form-data" action="index.php?page=add">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" placeholder="Movie title" required>
            </div>
            <div class="form-group">
                <label>Genre</label>
                <select name="genre" required>
                    <option value="">Select genre</option>
                    <?php foreach($genres as $g) echo "<option>$g</option>"; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Release Year</label>
                <input type="number" name="year" placeholder="e.g. 2024" required>
            </div>
            <div class="form-group">
                <label>Poster Image</label>
                <input type="file" name="poster" accept="image/*">
            </div>
            <div class="form-group">
                <label>Watch Link</label>
                <input type="text" name="link" placeholder="https://...">
            </div>
            <button name="submit" type="submit" style="width:100%;margin-top:8px;">Add Movie</button>
        </form>
    </div>
</div>

<?php
// ============================================================
//  PAGE: EDIT
// ============================================================
elseif ($page === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])):
    $id   = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM movies WHERE movie_id=?");
    $stmt->bindValue(1, $id);
    $data = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$data) { header("Location: index.php"); exit(); }
?>
<div class="page-header">
    <h1>Edit Movie</h1>
    <p>Update the details for this movie</p>
</div>
<div class="form-page">
    <div class="form-card">
        <form method="POST" enctype="multipart/form-data" action="index.php?page=edit&id=<?= $id ?>">
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($data['title']) ?>" required>
            </div>
            <div class="form-group">
                <label>Genre</label>
                <select name="genre">
                    <?php foreach($genres as $g): ?>
                        <option <?= $data['genre'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Release Year</label>
                <input type="number" name="year" value="<?= htmlspecialchars($data['release_year']) ?>" required>
            </div>
            <div class="form-group">
                <label>Poster Image <span style="color:#555;font-weight:300;">(leave blank to keep current)</span></label>
                <input type="file" name="poster" accept="image/*">
            </div>
            <div class="form-group">
                <label>Watch Link</label>
                <input type="text" name="link" value="<?= htmlspecialchars($data['link']) ?>" placeholder="https://...">
            </div>
            <button name="update" type="submit" style="width:100%;margin-top:8px;">Save Changes</button>
        </form>
    </div>
</div>

<?php
// ============================================================
//  PAGE: RATE
// ============================================================
elseif ($page === 'rate' && isset($_GET['id']) && is_numeric($_GET['id'])):
    $id    = (int)$_GET['id'];
    $stmt  = $conn->prepare("SELECT title FROM movies WHERE movie_id=?");
    $stmt->bindValue(1, $id);
    $movie = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$movie) { header("Location: index.php"); exit(); }
?>
<div class="page-header">
    <h1>Rate Movie</h1>
    <p><?= htmlspecialchars($movie['title']) ?></p>
</div>
<div class="form-page">
    <div class="form-card" style="text-align:center;">
        <form method="POST" action="index.php?page=rate&id=<?= $id ?>">
            <div id="stars" style="font-size:48px;margin-bottom:24px;letter-spacing:8px;">
                <span class="star" onclick="rate(1)">&#9733;</span>
                <span class="star" onclick="rate(2)">&#9733;</span>
                <span class="star" onclick="rate(3)">&#9733;</span>
                <span class="star" onclick="rate(4)">&#9733;</span>
                <span class="star" onclick="rate(5)">&#9733;</span>
            </div>
            <input type="hidden" name="rating" id="rating">
            <button type="submit" style="width:100%;">Submit Rating</button>
        </form>
    </div>
</div>
<script>
function rate(n) {
    document.getElementById("rating").value = n;
    document.querySelectorAll(".star").forEach((s,i) => s.classList.toggle("selected", i < n));
}
</script>

<?php endif; ?>

</body>
<script>
// ── Two-layer cursor ──────────────────────────────────────────
const glow = document.getElementById('cursor-glow');
const dot  = document.getElementById('cursor-dot');
document.addEventListener('mousemove', e => {
    glow.style.left = e.clientX + 'px';
    glow.style.top  = e.clientY + 'px';
    dot.style.left  = e.clientX + 'px';
    dot.style.top   = e.clientY + 'px';
});

// ── Floating particles ────────────────────────────────────────
const canvas = document.getElementById('particles');
const ctx    = canvas.getContext('2d');
let W, H, particles = [];
let mouse = { x: -9999, y: -9999 };

function resize() {
    W = canvas.width  = window.innerWidth;
    H = canvas.height = window.innerHeight;
}

function init() {
    particles = Array.from({ length: 70 }, () => ({
        x:  Math.random() * W,
        y:  Math.random() * H,
        r:  Math.random() * 1.3 + 0.3,
        vx: (Math.random() - 0.5) * 0.3,
        vy: (Math.random() - 0.5) * 0.3,
        a:  Math.random() * 0.35 + 0.1,
    }));
}

function draw() {
    ctx.clearRect(0, 0, W, H);
    particles.forEach(p => {
        // Gentle repulsion from cursor
        const dx   = p.x - mouse.x;
        const dy   = p.y - mouse.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 160 && dist > 0) {
            const force = (160 - dist) / 160 * 0.5;
            p.vx += (dx / dist) * force;
            p.vy += (dy / dist) * force;
        }
        // Speed cap + friction
        const spd = Math.sqrt(p.vx * p.vx + p.vy * p.vy);
        if (spd > 1.8) { p.vx *= 0.88; p.vy *= 0.88; }
        p.vx *= 0.99;
        p.vy *= 0.99;
        p.x  += p.vx;
        p.y  += p.vy;
        // Wrap edges
        if (p.x < 0) p.x = W;
        if (p.x > W) p.x = 0;
        if (p.y < 0) p.y = H;
        if (p.y > H) p.y = 0;
        // Draw
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(255,255,255,${p.a})`;
        ctx.fill();
    });
    requestAnimationFrame(draw);
}

document.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });
window.addEventListener('resize', () => { resize(); init(); });
resize();
init();
draw();
</script>
</html>