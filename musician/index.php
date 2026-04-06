<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Musician | Music Player</title>
    <link rel="icon"
          href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎶</text></svg>">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="container">
    <!-- Player Panel -->
    <div class="panel player">
        <div class="track-title">Musician</div>
        <div class="album-art">
            <img id="albumArt"
                 src="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🎶</text></svg>"
                 alt="Album Art">
        </div>

        <div class="track-info">
            <div class="track-title" id="trackTitle">No song is currently playing</div>
            <div class="track-artist" id="trackArtist">-</div>
        </div>

        <div class="progress-container">
            <div class="progress-bar" id="progressBar">
                <div class="progress" id="progress"></div>
            </div>
            <div class="time-info">
                <span id="currentTime">0:00</span>
                <span id="duration">0:00</span>
            </div>
        </div>

        <div class="controls">
            <button class="control-btn" onclick="previousTrack()">⏮</button>
            <button class="control-btn play" onclick="togglePlay()" id="playBtn">▶</button>
            <button class="control-btn" onclick="nextTrack()">⏭</button>
        </div>

        <audio id="audioPlayer"></audio>
    </div>

    <!-- Default Songs List -->
    <div class="panel">
        <div style="display: none">
            <h2>➕ Add New Song</h2>
            <div class="input-group">
                <label>Song JSON:</label>
                <textarea id="songJson"
                          placeholder='{"title":"Song Title","artist":"Song Artist","album":"Album Title","year":"2026","publisher":"Publisher Compony","cover":"https://example.com/song.jpg","link":"https://example.com/song.mp3"}'></textarea>
            </div>
            <button class="btn" onclick="addSong()">Add Song</button>
        </div>
        <h2>📀 Songs List</h2>
        <div class="song-list" id="defaultSongsList"></div>

    </div>

    <!-- Categories Panel -->
    <div class="panel">
        <h2>🏷️ Categories</h2>
        <div class="playlist-list" id="categoriesList"></div>

        <h2 style="margin-top: 30px;">📁 Playlists</h2>
        <div class="playlist-list" id="playlistsList"></div>

        <div style="display: block">
            <h2 style="margin-top: 30px;">➕ New Playlist</h2>
            <div class="input-group">
                <label>Playlist Name:</label>
                <input type="text" id="playlistName" placeholder="Example: My Favorites">
            </div>
            <button class="btn" onclick="createPlaylist()">Create Playlist</button>
        </div>
    </div>
</div>

<!-- Add to Playlist Modal -->
<div class="add-to-playlist-modal" id="addToPlaylistModal">
    <div class="modal-content">
        <h2 class="modal-title">Add to Playlist</h2>
        <div class="playlist-select">
            <select id="playlistSelect"></select>
        </div>
        <div class="modal-buttons">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn" onclick="confirmAddToPlaylist()">Add</button>
        </div>
    </div>
</div>
<script>
    // Default Songs
    var defaultSongs = <?=file_get_contents('assets/songs.json')?>;
    var defaultCategories = <?=file_get_contents('assets/categories.json')?>;
    var defaultPlaylists = <?=file_get_contents('assets/playlists.json')?>;
</script>
<script src="assets/script.js"></script>
</body>
</html>

<?php require_once __DIR__ . '/getter.php'; ?>
