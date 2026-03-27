// State Management
let state = {
    currentView: 'splash',
    selectedBank: null,
    selectedCategory: null,
    quizConfig: {
        count: 10,
        hasTimer: false,
        timerVal: 30
    },
    banks: [],
    categories: {},
    questions: [],
    currentQuestionIndex: 0,
    userAnswers: [],
    score: 0,
    startTime: 0,
    timerInterval: null
};

// Initialize App
document.addEventListener('DOMContentLoaded', async () => {
    // Splash timeout
    setTimeout(async () => {
        await fetchLiveData();
        showView('bank-selection');
        renderBanks();
    }, 2500);
});

async function fetchLiveData() {
    try {
        const response = await fetch('../api_mobile.php');
        const data = await response.json();
        state.banks = data.banks;
        state.categories = data.categories;
    } catch (e) {
        console.error('API Error:', e);
        // Fallback or alert
    }
}

// Navigation
window.showView = function(viewId) {
    document.querySelectorAll('.view').forEach(view => {
        view.classList.remove('active');
    });
    const nextView = document.getElementById(viewId);
    if (nextView) {
        nextView.classList.add('active');
        state.currentView = viewId;
    }
};

// Bank Rendering
function renderBanks() {
    const grid = document.getElementById('bank-grid');
    if (!state.banks.length) {
        grid.innerHTML = '<p style="text-align:center; padding: 2rem;">Hata: Veriler yüklenemedi.</p>';
        return;
    }
    grid.innerHTML = state.banks.map(bank => `
        <div class="bank-card" onclick="selectBank('${bank.id}')">
            <div class="bank-info">
                <h3>${bank.title}</h3>
                <span>${bank.count} Konu Mevcut</span>
            </div>
            <div class="bank-icon">${bank.icon}</div>
        </div>
    `).join('');
}

window.selectBank = function(bankId) {
    state.selectedBank = bankId;
    const bank = state.banks.find(b => b.id === bankId);
    document.getElementById('selected-bank-title').innerText = bank.title;
    renderCategories(bankId);
    showView('category-selection');
};

function renderCategories(bankId) {
    const list = document.getElementById('category-list');
    const categories = state.categories[bankId] || [];
    list.innerHTML = categories.map(cat => `
        <div class="category-item" onclick="selectCategory('${cat}')">
            <span>${cat}</span>
            <small>Soruları Gör →</small>
        </div>
    `).join('');
}

window.selectCategory = function(catName) {
    state.selectedCategory = catName;
    document.getElementById('selected-category-title').innerText = catName;
    showView('quiz-setup');
};

// Quiz Logic
window.startQuiz = async function() {
    state.quizConfig.count = parseInt(document.getElementById('question-count').value);
    state.quizConfig.hasTimer = document.getElementById('timer-toggle').checked;
    
    // Show loading on start btn
    const startBtn = document.querySelector('.start-quiz-btn');
    startBtn.innerText = 'Yükleniyor...';
    startBtn.disabled = true;

    try {
        const response = await fetch(`../api_questions.php?bank=${encodeURIComponent(state.selectedBank)}&category=${encodeURIComponent(state.selectedCategory)}&count=${state.quizConfig.count}`);
        const data = await response.json();
        
        if (data.error) throw new Error(data.error);
        
        // Prepare questions
        state.questions = data.map(q => {
            // Shuffle options locally
            let options = q.options.map((text, index) => ({ text, index }));
            for (let i = options.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [options[i], options[j]] = [options[j], options[i]];
            }
            return {
                ...q,
                shuffledOptions: options,
                newCorrectIndex: options.findIndex(o => o.index === parseInt(q.correct) || o.index === q.correct)
            };
        });

        state.currentQuestionIndex = 0;
        state.userAnswers = [];
        state.score = 0;
        state.startTime = Date.now();
        
        showView('quiz');
        loadQuestion(0);
    } catch (e) {
        alert('Sorular yüklenirken bir hata oluştu: ' + e.message);
    } finally {
        startBtn.innerText = 'Alıştırmaya Başla';
        startBtn.disabled = false;
    }
};

function loadQuestion(index) {
    const question = state.questions[index];
    const container = document.getElementById('question-text');
    const optionsGrid = document.getElementById('options-container');
    const counter = document.getElementById('quiz-counter');
    const progress = document.getElementById('quiz-progress');

    container.innerText = question.text;
    counter.innerText = `${index + 1} / ${state.questions.length}`;
    progress.style.transform = `scaleX(${(index + 1) / state.questions.length})`;

    optionsGrid.innerHTML = question.shuffledOptions.map((opt, i) => `
        <button class="option-btn" id="opt-${i}" onclick="submitAnswer(${i})">
            <div class="letter-box">${String.fromCharCode(65 + i)}</div>
            ${opt.text}
        </button>
    `).join('');

    // Reset UI
    document.getElementById('next-btn').classList.remove('enabled');
    document.getElementById('next-btn').disabled = true;
    document.getElementById('feedback-msg').classList.remove('show');

    if (state.quizConfig.hasTimer) startTimer();
    else document.getElementById('quiz-timer-display').innerText = '∞';
}

function startTimer() {
    clearInterval(state.timerInterval);
    let time = state.quizConfig.timerVal;
    const display = document.getElementById('quiz-timer-display');
    display.innerText = time;
    
    state.timerInterval = setInterval(() => {
        time--;
        display.innerText = time;
        if (time <= 0) {
            clearInterval(state.timerInterval);
            submitAnswer(-1); // Timeout
        }
    }, 1000);
}

window.submitAnswer = function(index) {
    if (state.userAnswers[state.currentQuestionIndex] !== undefined) return;
    
    clearInterval(state.timerInterval);
    const question = state.questions[state.currentQuestionIndex];
    const isCorrect = (index === question.newCorrectIndex);
    
    state.userAnswers[state.currentQuestionIndex] = index;
    if (isCorrect) state.score += 10;

    // Show feedback
    const btn = document.getElementById(`opt-${index}`);
    const correctBtn = document.getElementById(`opt-${question.newCorrectIndex}`);
    
    if (index !== -1) {
        btn.classList.add(isCorrect ? 'correct' : 'wrong');
        if (!isCorrect) {
            correctBtn.classList.add('correct');
        }
    } else {
        correctBtn.classList.add('correct');
    }

    const feedback = document.getElementById('feedback-msg');
    feedback.innerText = isCorrect ? '✅ Harika! Doğru cevap.' : '❌ Yanlış cevap!';
    feedback.style.color = isCorrect ? '#10b981' : '#ef4444';
    feedback.classList.add('show');

    document.getElementById('next-btn').classList.add('enabled');
    document.getElementById('next-btn').disabled = false;
    
    if (index !== -1 && !isCorrect) {
        if (window.navigator.vibrate) window.navigator.vibrate(200);
    }
};

window.nextQuestion = function() {
    if (state.currentQuestionIndex < state.questions.length - 1) {
        state.currentQuestionIndex++;
        loadQuestion(state.currentQuestionIndex);
    } else {
        showResults();
    }
};

function showResults() {
    showView('result');
    const correctCount = state.userAnswers.filter((ans, i) => ans === state.questions[i].newCorrectIndex).length;
    const scoreVal = Math.round((correctCount / state.questions.length) * 100);
    
    document.getElementById('result-score').innerText = scoreVal;
    document.getElementById('result-score-circle').style.strokeDasharray = `${scoreVal}, 100`;
    document.getElementById('stat-correct').innerText = correctCount;
    document.getElementById('stat-wrong').innerText = state.questions.length - correctCount;
    
    const duration = Math.round((Date.now() - state.startTime) / 1000);
    const minutes = Math.floor(duration / 60);
    const seconds = duration % 60;
    document.getElementById('stat-time').innerText = `${minutes}m ${seconds}s`;

    const status = document.getElementById('result-status');
    if (scoreVal >= 80) status.innerText = 'Mükemmel! 🎉';
    else if (scoreVal >= 50) status.innerText = 'Çok İyi! 👍';
    else status.innerText = 'Daha Çok Çalışmalısın! 📚';
}

window.quitQuiz = function() {
    if (confirm('Alıştırmayı sonlandırmak istiyor musunuz?')) {
        clearInterval(state.timerInterval);
        showView('bank-selection');
    }
};

window.shareResult = function() {
    const text = `Bir Soru Bir Sevap uygulamasında %${document.getElementById('result-score').innerText} başarı elde ettim! #BirSoruBirSevap`;
    if (navigator.share) {
        navigator.share({
            title: 'Başarı Belgem',
            text: text,
            url: window.location.href
        });
    } else {
        alert('Paylaşıldı: ' + text);
    }
};
