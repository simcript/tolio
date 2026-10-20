// State
let currentSong = null;
let currentPlaylist = null;
let isPlaying = false;
let selectedSongForPlaylist = null;

const audioPlayer = document.getElementById('audioPlayer');

// Load from LocalStorage
function loadData() {
    var songs = localStorage.getItem('defaultSongs');
    var playlists = localStorage.getItem('playlists');
    var categories = localStorage.getItem('defaultCategories');

    if (!songs) {
        localStorage.setItem('defaultSongs', JSON.stringify([]));
        songs = JSON.parse(localStorage.getItem('defaultSongs'));
    } else {
        songs = JSON.parse(songs);
    }
    if (!playlists) {
        localStorage.setItem('playlists', JSON.stringify([]));
        playlists = JSON.parse(localStorage.getItem('playlists'));
    } else {
        playlists = JSON.parse(playlists);
    }
    if (!categories) {
        localStorage.setItem('categories', JSON.stringify([]));
        categories = JSON.parse(localStorage.getItem('categories'));
    } else {
        categories = JSON.parse(categories);
    }
    songs.forEach(song => defaultSongs.push(song));
    playlists.forEach(plist => defaultPlaylists.push(plist));
    categories.forEach(clist => defaultCategories.push(clist));
}

// Get Songs
function getSongs() {
    return defaultSongs;
}

// Get Playlists
function getPlaylists() {
    return defaultPlaylists;
}

// Get Categories
function getCategories() {
    return defaultCategories;
}

// Save Songs
function saveSongs(songs) {
    localStorage.setItem('defaultSongs', JSON.stringify(songs));
}

// Save Playlists
function savePlaylists(playlists) {
    localStorage.setItem('playlists', JSON.stringify(playlists));
}

// Render Default Songs
function renderDefaultSongs() {
    const songs = getSongs();
    const container = document.getElementById('defaultSongsList');
    container.innerHTML = '';

    songs.forEach((song, index) => {
        const item = document.createElement('div');
        item.className = 'song-item';
        if (currentSong && currentSong.link === song.link) {
            item.classList.add('active');
        }

        item.innerHTML = `
                    <img src="${song.cover || 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 50 50\'%3E%3Crect fill=\'%23667eea\' width=\'50\' height=\'50\'/%3E%3C/svg%3E'}" 
                         class="song-cover" alt="${song.title}">
                    <div class="song-details">
                        <div class="song-name">${song.title}</div>
                        <div class="song-artist">${song.artist}</div>
                    </div>
                    <div class="song-actions">
                        <button class="action-btn" onclick="playSong(${index}, null, 0)">▶</button>
                        <button class="action-btn" onclick="openAddToPlaylistModal(${index}, null)">+</button>
                    </div>
                `;

        container.appendChild(item);
    });
}

// Render Playlists
function renderPlaylists() {
    const playlists = getPlaylists();
    const container = document.getElementById('playlistsList');
    container.innerHTML = '';

    playlists.forEach((playlist, index) => {
        const item = document.createElement('div');
        item.className = 'playlist-item';
        item.onclick = () => showPlaylist(index);

        item.innerHTML = `
                    <div class="song-details">
                        <div class="song-name">📁 ${playlist.name}</div>
                        <div class="song-artist">${playlist.songs.length} songs</div>
                    </div>
                `;

        container.appendChild(item);
    });
}

// Render Categories
function renderCategories() {
    const categories = getCategories();
    const container = document.getElementById('categoriesList');
    container.innerHTML = '';

    categories.forEach((category, index) => {
        const item = document.createElement('div');
        item.className = 'playlist-item';
        item.onclick = () => showCategory(index);

        item.innerHTML = `
                    <div class="song-details">
                        <div class="song-name">🏷️ ${category.name}</div>
                    </div>
                `;

        container.appendChild(item);
    });
}


// Audio Player Events
audioPlayer.addEventListener('timeupdate', () => {
    const progress = (audioPlayer.currentTime / audioPlayer.duration) * 100;
    document.getElementById('progress').style.width = progress + '%';
    document.getElementById('currentTime').textContent = formatTime(audioPlayer.currentTime);
});

audioPlayer.addEventListener('loadedmetadata', () => {
    document.getElementById('duration').textContent = formatTime(audioPlayer.duration);
});

audioPlayer.addEventListener('ended', nextTrack);

// Progress Bar Click
document.getElementById('progressBar').addEventListener('click', (e) => {
    const bar = e.currentTarget;
    const clickX = e.offsetX;
    const width = bar.offsetWidth;
    const percentage = clickX / width;
    audioPlayer.currentTime = percentage * audioPlayer.duration;
});


// Format Time
function formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// ADD Song
function addSong() {
    const jsonInput = document.getElementById('songJson').value.trim();

    try {
        const song = JSON.parse(jsonInput);

        if (!song.title || !song.link) {
            alert('Please enter at least the song title and link');
            return;
        }

        const songs = getSongs();
        songs.push(song);
        saveSongs(songs);

        document.getElementById('songJson').value = '';
        renderDefaultSongs();
        alert('Song added successfully!');
    } catch (e) {
        alert('Invalid JSON format!');
    }
}

// CREATE Playlist
function createPlaylist() {
    const name = document.getElementById('playlistName').value.trim();

    if (!name) {
        alert('Please enter a playlist name');
        return;
    }

    const playlists = getPlaylists();
    playlists.push({ name: name, songs: [] });
    savePlaylists(playlists);

    document.getElementById('playlistName').value = '';
    renderPlaylists();
    alert('Playlist created successfully!');
}

// SHOW Playlist
function showPlaylist(playlistIndex) {
    currentPlaylist = playlistIndex;

    const playlists = getPlaylists();
    const playlist = playlists[playlistIndex];

    const container = document.getElementById('defaultSongsList');
    container.innerHTML = `<h3 style="color: #667eea; margin-bottom: 15px;">📁 ${playlist.name}</h3>`;

    if (playlist.songs.length === 0) {
        container.innerHTML += '<p style="text-align: center; color: #999;">This playlist is empty</p>';
        return;
    }

    playlist.songs.forEach((song, index) => {
        const item = document.createElement('div');
        item.className = 'song-item';

        if (currentSong && currentSong.link === song.link) {
            item.classList.add('active');
        }

        item.innerHTML = `
            <img src="${song.cover || 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 50 50\'%3E%3Crect fill=\'%23667eea\' width=\'50\' height=\'50\'/%3E%3C/svg%3E'}" 
                 class="song-cover" alt="${song.title}">
            <div class="song-details">
                <div class="song-name">${song.title}</div>
                <div class="song-artist">${song.artist}</div>
            </div>
            <div class="song-actions">
                <button class="action-btn" onclick="playSong(${index}, ${playlistIndex}, 2)">▶</button>
                <button class="action-btn" onclick="removeFromPlaylist(${playlistIndex}, ${index})">🗑</button>
            </div>
        `;
        container.appendChild(item);
    });

    const backBtn = document.createElement('button');
    backBtn.className = 'btn';
    backBtn.style.marginTop = '15px';
    backBtn.textContent = '🔙 Back to Main List';
    backBtn.onclick = () => {
        currentPlaylist = null;
        renderDefaultSongs();
    };
    container.appendChild(backBtn);
}

// SHOW Category
function showCategory(categoryIndex) {
    currentCategory = categoryIndex;

    const categories = getCategories();
    const category = categories[categoryIndex];

    const container = document.getElementById('defaultSongsList');
    container.innerHTML = `<h3 style="color: #667eea; margin-bottom: 15px;">📁 ${category.name}</h3>`;

    if (category.songs.length === 0) {
        container.innerHTML += '<p style="text-align: center; color: #999;">This category is empty</p>';
        return;
    }

    category.songs.forEach((song, index) => {
        const item = document.createElement('div');
        item.className = 'song-item';

        if (currentSong && currentSong.link === song.link) {
            item.classList.add('active');
        }

        item.innerHTML = `
            <img src="${song.cover || 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 50 50\'%3E%3Crect fill=\'%23667eea\' width=\'50\' height=\'50\'/%3E%3C/svg%3E'}" 
                 class="song-cover" alt="${song.title}">
            <div class="song-details">
                <div class="song-name">${song.title}</div>
                <div class="song-artist">${song.artist}</div>
            </div>
            <div class="song-actions">
                <button class="action-btn" onclick="playSong(${index}, ${categoryIndex}, 1)">▶</button>
                 <button class="action-btn" onclick="openAddToPlaylistModal(${index}, null)">+</button>
            </div>
        `;
        container.appendChild(item);
    });

    const backBtn = document.createElement('button');
    backBtn.className = 'btn';
    backBtn.style.marginTop = '15px';
    backBtn.textContent = '🔙 Back to Main List';
    backBtn.onclick = () => {
        currentCategory = null;
        renderDefaultSongs();
    };
    container.appendChild(backBtn);
}

// Play Song
function playSong(songIndex, playlistIndex, source) {
    let song;

    if (playlistIndex !== null) {
        if (source === 2) {
            const playlists = getPlaylists();
            song = playlists[playlistIndex].songs[songIndex];
        } else {
            const categories = getCategories();
            song = categories[playlistIndex].songs[songIndex];
        }
        currentPlaylist = playlistIndex;
    } else {
        const songs = getSongs();
        song = songs[songIndex];
        currentPlaylist = null;
    }

    currentSong = song;
    audioPlayer.src = song.link;
    audioPlayer.play();
    isPlaying = true;

    document.getElementById('trackTitle').textContent = song.title;
    document.getElementById('trackArtist').textContent = song.artist || '-';
    document.getElementById('albumArt').src =
        song.cover ||
        'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 200\'%3E%3Crect fill=\'%23667eea\' width=\'200\' height=\'200\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'Arial\' font-size=\'60\' fill=\'white\'%3E♫%3C/text%3E%3C/svg%3E';

    document.getElementById('playBtn').textContent = '⏸';

    if (currentPlaylist !== null) {
        showPlaylist(currentPlaylist);
    } else {
        renderDefaultSongs();
    }
}

// Toggle Play/Pause
function togglePlay() {
    if (!currentSong) {
        alert('Please select a song first');
        return;
    }

    if (isPlaying) {
        audioPlayer.pause();
        document.getElementById('playBtn').textContent = '▶';
    } else {
        audioPlayer.play();
        document.getElementById('playBtn').textContent = '⏸';
    }

    isPlaying = !isPlaying;
}

// Next/Previous Track
function nextTrack() {
    if (!currentSong) return;

    let currentArray = getSongs();
    let sourceParam = 0;
    let listIndex = null;

    if (currentPlaylist !== null) {
        const playlists = getPlaylists();
        const categories = getCategories();

        if (playlists[currentPlaylist] && playlists[currentPlaylist].songs.some(s => s.link === currentSong.link)) {
            currentArray = playlists[currentPlaylist].songs;
            sourceParam = 2; // Source 2 = Playlist
            listIndex = currentPlaylist;
        } else if (categories[currentPlaylist] && categories[currentPlaylist].songs.some(s => s.link === currentSong.link)) {
            currentArray = categories[currentPlaylist].songs;
            sourceParam = 1; // Source 1 = Category
            listIndex = currentPlaylist;
        }
    }

    let currentIndex = currentArray.findIndex(s => s.link === currentSong.link);

    if (currentIndex === -1) {
        currentArray = getSongs();
        sourceParam = 0; // Source 0 = Default List
        listIndex = null;
        currentIndex = currentArray.findIndex(s => s.link === currentSong.link);
    }

    if (currentIndex !== -1) {
        let nextIndex = (currentIndex + 1) % currentArray.length;
        playSong(nextIndex, listIndex, sourceParam);
    }
}

function previousTrack() {
    if (!currentSong) return;

    let currentArray = getSongs();
    let sourceParam = 0;
    let listIndex = null;

    if (currentPlaylist !== null) {
        const playlists = getPlaylists();
        const categories = getCategories();

        if (playlists[currentPlaylist] && playlists[currentPlaylist].songs.some(s => s.link === currentSong.link)) {
            currentArray = playlists[currentPlaylist].songs;
            sourceParam = 2;
            listIndex = currentPlaylist;
        } else if (categories[currentPlaylist] && categories[currentPlaylist].songs.some(s => s.link === currentSong.link)) {
            currentArray = categories[currentPlaylist].songs;
            sourceParam = 1;
            listIndex = currentPlaylist;
        }
    }

    let currentIndex = currentArray.findIndex(s => s.link === currentSong.link);

    if (currentIndex === -1) {
        currentArray = getSongs();
        sourceParam = 0;
        listIndex = null;
        currentIndex = currentArray.findIndex(s => s.link === currentSong.link);
    }

    // پخش آهنگ قبلی (با قابلیت چرخش به آهنگ آخر در صورت رسیدن به ابتدای لیست)
    if (currentIndex !== -1) {
        let prevIndex = (currentIndex - 1 + currentArray.length) % currentArray.length;
        playSong(prevIndex, listIndex, sourceParam);
    }
}

// ADD TO Playlist Modal
function openAddToPlaylistModal(songIndex, playlistIndex) {
    selectedSongForPlaylist = { songIndex, playlistIndex };

    const playlists = getPlaylists();
    const select = document.getElementById('playlistSelect');
    select.innerHTML = '';

    playlists.forEach((playlist, index) => {
        const option = document.createElement('option');
        option.value = index;
        option.textContent = playlist.name;
        select.appendChild(option);
    });

    document.getElementById('addToPlaylistModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('addToPlaylistModal').style.display = 'none';
}

function confirmAddToPlaylist() {
    const targetPlaylistIndex = parseInt(document.getElementById('playlistSelect').value);
    const { songIndex, playlistIndex } = selectedSongForPlaylist;

    let song;

    if (playlistIndex !== null) {
        const playlists = getPlaylists();
        song = playlists[playlistIndex].songs[songIndex];
    } else {
        const songs = getSongs();
        song = songs[songIndex];
    }

    const playlists = getPlaylists();
    playlists[targetPlaylistIndex].songs.push(song);
    savePlaylists(playlists);

    closeModal();
    alert('Song successfully added to playlist!');
}

// REMOVE FROM Playlist
function removeFromPlaylist(playlistIndex, songIndex) {
    if (confirm('Are you sure you want to remove this song from the playlist?')) {
        const playlists = getPlaylists();
        playlists[playlistIndex].songs.splice(songIndex, 1);
        savePlaylists(playlists);
        showPlaylist(playlistIndex);
    }
}

// Initialize
loadData();
renderDefaultSongs();
renderPlaylists();
renderCategories();
