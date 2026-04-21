// js/reminders.js

const reminderSound = new Audio('assets/notify.mp3');

function getLocalDateString(date) {
    let d = new Date(date);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

async function checkAndTriggerReminders() {
    const now = new Date();
    const todayStr = getLocalDateString(now); 
    const currentTime = now.getHours().toString().padStart(2, '0') + ":" + 
                        now.getMinutes().toString().padStart(2, '0');

    // 1. Retrieve the Schedule from LocalStorage (Fixes Navigation Issue)
    const storedSchedule = localStorage.getItem('studySync_virtualSchedule');
    if (!storedSchedule) return;
    
    const vSchedule = JSON.parse(storedSchedule);
    const todayTasks = vSchedule[todayStr] || [];

    todayTasks.forEach(task => {
        if (parseInt(task.reminder) !== 1 || task.status !== 'pending') return;

        let triggerTime = "";

        // CASE A: Specific Time Provided
        if (task.task_time && task.task_time !== "00:00:00") {
            triggerTime = task.task_time.substring(0, 5);
        } 
        // CASE B & C: All-day or Overdue (The engine puts them in today)
        else {
            triggerTime = "09:00";
        }

        // EXECUTION
        if (triggerTime === currentTime) {
            // Unique key: task_id + date + time to prevent repeat alerts in the same minute
            const notifyKey = `notified_${task.id}_${todayStr}_${currentTime}`;
            if (sessionStorage.getItem(notifyKey)) return;

            // Trigger sound and notifications
            playReminderSound();
            
            if (Notification.permission === "granted") {
                new Notification("StudySync Alert", {
                    body: `Task: ${task.title}\nSchedule: ${triggerTime === "09:00" ? 'Morning Focus' : triggerTime}`,
                    icon: "Image_Folder/Logo.png"
                });
            }

            showToast(`Time to start: ${task.title}`, "info");
            sessionStorage.setItem(notifyKey, "true");
        }
    });

    updateReminderUI(todayTasks);
}

// Update the Bell Icon and Dropdown
function updateReminderUI(todayTasks) {
    const bellDot = document.getElementById('active-reminder-dot');
    const list = document.getElementById('reminder-list');
    if (!list) return;

    const reminders = todayTasks.filter(t => parseInt(t.reminder) === 1 && t.status === 'pending');

    if (reminders.length > 0) {
        if (bellDot) bellDot.classList.remove('hidden');
        list.innerHTML = reminders.map(t => {
            const timeDisplay = (t.task_time && t.task_time !== "00:00:00") 
                                ? t.task_time.substring(0,5) 
                                : "09:00 (All Day)";
            return `
            <div class="p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-white/5 transition-colors flex items-center gap-3">
                <div class="w-2 h-2 rounded-full" style="background:${t.color}"></div>
                <div class="flex-1">
                    <p class="text-[11px] font-bold dark:text-white">${t.title}</p>
                    <p class="text-[9px] text-slate-400">${timeDisplay} • ${t.subject}</p>
                </div>
            </div>`;
        }).join('');
    } else {
        if (bellDot) bellDot.classList.add('hidden');
        list.innerHTML = `<p class="text-center py-8 text-[10px] text-slate-400 italic">No reminders for today</p>`;
    }
}

// HELPER FUNCTIONS
function playReminderSound() {
    if (localStorage.getItem('mute_reminders') !== 'true') {
        reminderSound.play().catch(() => console.log("Waiting for user interaction to play audio."));
    }
}

function toggleReminderDropdown() {
    const dropdown = document.getElementById('reminder-dropdown');
    if (dropdown) dropdown.classList.toggle('hidden');
}

function toggleReminderSound() {
    const btn = document.getElementById('sound-toggle-btn');
    const isMuted = localStorage.getItem('mute_reminders') === 'true';
    if (isMuted) {
        localStorage.setItem('mute_reminders', 'false');
        reminderSound.play();
    } else {
        localStorage.setItem('mute_reminders', 'true');
    }
    updateSoundIcon();
}

function updateSoundIcon() {
    const btn = document.getElementById('sound-toggle-btn');
    if (!btn) return;
    const isMuted = localStorage.getItem('mute_reminders') === 'true';
    btn.innerHTML = isMuted ? '<i class="fas fa-volume-mute text-xs text-rose-500"></i>' : '<i class="fas fa-volume-up text-xs text-sync"></i>';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', updateSoundIcon);

// Interval check every 30 seconds
setInterval(checkAndTriggerReminders, 30000);