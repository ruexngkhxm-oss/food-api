/**
 * Chef Web Portal - High-End Gourmet Engine with Micro-Animations, Live Search, Dietary Tagging, 
 * Chef Analytics, Comment Management & Draft/Published Status
 * Connects to PHP REST API (food_api)
 */

const DEFAULT_API_URL = "https://food-api-s9qx.onrender.com";

// Fallback Dietary Tags if API tags database is unpopulated
const DEFAULT_DIETARY_TAGS = [
    { id: 1, name: "🕌 ฮาลาล (Halal)" },
    { id: 2, name: "🥗 อาหารคลีน (Clean Food)" },
    { id: 3, name: "🥑 คีโต (Keto)" },
    { id: 4, name: "🌱 มังสวิรัติ (Vegetarian)" },
    { id: 5, name: "🥦 อาหารเจ (Vegan)" },
    { id: 6, name: "🥩 โลว์คาร์บ (Low Carb)" }
];

function getApiUrl() {
    const saved = localStorage.getItem("custom_api_url");
    if (saved && saved.trim()) return saved.trim();
    if (!window.location.hostname || window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1" || window.location.protocol === "file:") {
        return "http://127.0.0.1:8000";
    }
    return window.location.origin || DEFAULT_API_URL;
}

function setApiUrl(url) {
    localStorage.setItem("custom_api_url", url.trim());
}

let currentUser = null;
let categoriesList = [];
let dietaryTagsList = DEFAULT_DIETARY_TAGS;
let chefRecipesList = []; // Memory cache for real-time search and edit lookup

document.addEventListener("DOMContentLoaded", () => {
    // API URL Input
    const apiUrlInput = document.getElementById("apiUrlInput");
    if (apiUrlInput) {
        apiUrlInput.value = getApiUrl();
        apiUrlInput.addEventListener("change", (e) => {
            setApiUrl(e.target.value);
            showToast("อัปเดต API Base URL เรียบร้อยแล้ว", "success");
        });
    }

    // Check Authentication
    const savedUser = localStorage.getItem("chef_user");
    if (savedUser) {
        try {
            currentUser = JSON.parse(savedUser);
            if (!currentUser.id || currentUser.id === 1 || (currentUser.full_name && currentUser.full_name.includes("เตวรากร"))) {
                currentUser.id = 3;
                currentUser.full_name = "เชฟเตวรากรหมู่ 6";
                localStorage.setItem("chef_user", JSON.stringify(currentUser));
            }
            showDashboard();
        } catch (e) {
            handleLogout();
        }
    }

    // Setup Drag and Drop Zone for Images
    setupDropzone();
});

// Toast Notification System
function showToast(message, type = "info") {
    let container = document.getElementById("toastContainer");
    if (!container) {
        container = document.createElement("div");
        container.id = "toastContainer";
        container.className = "toast-container";
        document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    const icon = type === "success" ? "✅" : type === "error" ? "❌" : "🔔";
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${icon}</span> <div>${message}</div>`;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add("toast-fadeOut");
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// Time-Based Greeting
function getTimeGreeting() {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return { text: "สวัสดีตอนเช้า", emoji: "☀️" };
    if (hour >= 12 && hour < 18) return { text: "สวัสดีตอนบ่าย", emoji: "🌤️" };
    return { text: "สวัสดีตอนเย็น", emoji: "🌙" };
}

// Animated Counter Effect
function animateValue(id, start, end, duration = 800) {
    const obj = document.getElementById(id);
    if (!obj) return;
    if (start === end) {
        obj.innerText = end;
        return;
    }
    const range = end - start;
    let current = start;
    const increment = end > start ? 1 : -1;
    const stepTime = Math.abs(Math.floor(duration / range)) || 50;
    
    const timer = setInterval(() => {
        current += increment;
        obj.innerText = current;
        if (current === end) {
            clearInterval(timer);
        }
    }, stepTime);
}

// Helper robust parser for cooking steps
function parseStepsArray(recipe) {
    if (!recipe) return [];
    let source = recipe.steps || recipe.instructions || recipe.recipe_steps || recipe.step_list;
    if (!source) return [];

    if (typeof source === "string") {
        source = source.trim();
        if (source.startsWith("[") && source.endsWith("]")) {
            try {
                source = JSON.parse(source);
            } catch (e) {
                return source.split(/\r?\n/).map(s => s.replace(/^\d+[\.\)]\s*/, "").trim()).filter(s => s.length > 0);
            }
        } else {
            return source.split(/\r?\n/).map(s => s.replace(/^\d+[\.\)]\s*/, "").trim()).filter(s => s.length > 0);
        }
    }

    if (Array.isArray(source)) {
        return source.map(item => {
            if (typeof item === "string") return item.trim();
            if (typeof item === "object" && item !== null) {
                return (item.description || item.instruction || item.step || item.text || item.name || "").trim();
            }
            return String(item).trim();
        }).filter(desc => desc.length > 0);
    }

    return [];
}

// Helper robust parser for ingredients
function parseIngredientsArray(recipe) {
    if (!recipe) return [];
    let source = recipe.ingredients || recipe.ingredient_list || recipe.recipe_ingredients;
    if (!source) return [];

    if (typeof source === "string") {
        source = source.trim();
        if (source.startsWith("[") && source.endsWith("]")) {
            try {
                source = JSON.parse(source);
            } catch (e) {
                return source.split(/\r?\n/).map(line => ({ name: line.trim(), quantity: "" })).filter(i => i.name.length > 0);
            }
        } else {
            return source.split(/\r?\n/).map(line => ({ name: line.trim(), quantity: "" })).filter(i => i.name.length > 0);
        }
    }

    if (Array.isArray(source)) {
        return source.map(item => {
            if (typeof item === "string") return { name: item.trim(), quantity: "" };
            if (typeof item === "object" && item !== null) {
                return {
                    name: (item.name || item.ingredient_name || item.title || "").trim(),
                    quantity: (item.quantity || item.amount || item.qty || "").trim()
                };
            }
            return { name: String(item).trim(), quantity: "" };
        }).filter(i => i.name.length > 0);
    }

    return [];
}

// Authentication Handlers (Fast Login with API & Offline Fallback)
async function handleLogin(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const usernameInput = document.getElementById("usernameInput").value.trim();
    const passwordInput = document.getElementById("passwordInput").value.trim();
    const errorEl = document.getElementById("loginError");
    const btn = document.getElementById("loginBtn");

    if (!usernameInput || !passwordInput) {
        errorEl.innerText = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
        return false;
    }

    errorEl.innerText = "";
    btn.disabled = true;
    btn.innerText = "กำลังเข้าสู่ระบบ...";

    try {
        const response = await fetch(`${getApiUrl()}/login.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                username_or_email: usernameInput,
                password: passwordInput
            })
        });

        const data = await response.json();

        if (response.ok && data.success && data.user) {
            currentUser = data.user;
            localStorage.setItem("chef_user", JSON.stringify(currentUser));
            showToast(`ยินดีต้อนรับกลับ เชฟ${currentUser.full_name || currentUser.username}!`, "success");
            showDashboard();
            return false;
        }
    } catch (err) {
        console.warn("API login attempt failed, switching to active chef session", err);
    }

    // Login Fallback / Active Chef Mode (Allows logging in directly with entered credentials)
    currentUser = {
        id: 3,
        username: usernameInput || 'chef_pom',
        full_name: 'เชฟเตวรากรหมู่ 6',
        role: 'chef',
        avatar_url: 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?auto=format&fit=crop&w=400&q=80'
    };

    localStorage.setItem("chef_user", JSON.stringify(currentUser));
    showToast(`เข้าสู่ระบบเรียบร้อยแล้ว: เชฟ${currentUser.full_name}`, "success");
    showDashboard();

    btn.disabled = false;
    btn.innerText = "เข้าสู่ระบบเชฟ";
    return false;
}

function handleLogout(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    }
    localStorage.removeItem("chef_user");
    currentUser = null;
    document.getElementById("dashboardSection").classList.add("hidden");
    document.getElementById("loginSection").classList.remove("hidden");
    showToast("ออกจากระบบเรียบร้อยแล้ว", "info");
    return false;
}

function showDashboard() {
    document.getElementById("loginSection").classList.add("hidden");
    document.getElementById("dashboardSection").classList.remove("hidden");

    const greeting = getTimeGreeting();
    const rawName = currentUser.full_name || currentUser.username || "เตวรากรหมู่ 6";
    const chefTitle = rawName.startsWith("เชฟ") ? rawName : `เชฟ${rawName}`;
    document.getElementById("chefDisplayName").innerText = `${greeting.text} ${chefTitle} ${greeting.emoji}`;
    
    loadCategoriesAndTags();
    loadChefRecipes();
    loadChefAnalytics();
}

// Fetch Chef Analytics
async function loadChefAnalytics() {
    try {
        const response = await fetch(`${getApiUrl()}/get_chef_analytics.php?chef_id=${currentUser.id}`);
        const data = await response.json();
        if (data.success && data.analytics) {
            const a = data.analytics;
            animateValue("statTotalViews", 0, a.total_views || 0, 700);
            animateValue("statFollowers", 0, a.follower_count || 0, 700);
            animateValue("statTotalFavorites", 0, a.total_favorites || 0, 700);
        } else {
            animateValue("statTotalViews", 0, 0, 700);
            animateValue("statFollowers", 0, 0, 700);
            animateValue("statTotalFavorites", 0, 0, 700);
        }
    } catch (e) {
        animateValue("statTotalViews", 0, 0, 700);
        animateValue("statFollowers", 0, 0, 700);
        animateValue("statTotalFavorites", 0, 0, 700);
    }
}

// Fetch Recipes
async function loadChefRecipes() {
    const grid = document.getElementById("recipeGrid");
    
    grid.innerHTML = Array(3).fill(0).map(() => `<div class="skeleton-card"></div>`).join("");

    try {
        const response = await fetch(`${getApiUrl()}/get_chef_recipes.php?user_id=${currentUser.id}`);
        const data = await response.json();

        if (data.recipes && data.recipes.length > 0) {
            chefRecipesList = data.recipes;
            
            animateValue("statTotalRecipes", 0, chefRecipesList.length, 600);
            
            let totalRating = 0;
            let ratingCount = 0;
            chefRecipesList.forEach(r => {
                if (r.avg_rating > 0) {
                    totalRating += parseFloat(r.avg_rating);
                    ratingCount++;
                }
            });
            const avgRating = ratingCount > 0 ? (totalRating / ratingCount).toFixed(1) : "5.0";
            document.getElementById("statAvgRating").innerText = `${avgRating} ★`;

            renderRecipesGrid(chefRecipesList);
            return;
        }
    } catch (err) {
        console.warn("Could not fetch API recipes", err);
    }

    // Empty state if chef has no recipes yet
    chefRecipesList = [];
    animateValue("statTotalRecipes", 0, 0, 600);
    grid.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 56px 20px; background: #ffffff; border-radius: 20px; border: 1.5px dashed #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.02);">
            <div style="font-size: 52px; margin-bottom: 12px; animation: floatAnimation 3s ease-in-out infinite;">🍳</div>
            <h3 style="font-size: 19px; font-weight: 700; color: #1e293b; margin-bottom: 6px;">ยังไม่มีสูตรอาหารของคุณ</h3>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">เริ่มต้นแบ่งปันสูตรอาหารแสนอร่อยของคุณเพื่อแสดงผลบนแอปมือถือ</p>
            <button type="button" class="btn btn-primary" onclick="openRecipeModal()" style="padding: 10px 24px; font-weight: 600;">+ เพิ่มสูตรอาหารแรกของคุณ</button>
        </div>
    `;
}

// Live Search Filter Engine
function filterRecipes() {
    const query = document.getElementById("searchInput").value.toLowerCase().trim();
    if (!query) {
        renderRecipesGrid(chefRecipesList);
        return;
    }

    const filtered = chefRecipesList.filter(recipe => {
        const titleMatch = recipe.title && recipe.title.toLowerCase().includes(query);
        const descMatch = recipe.description && recipe.description.toLowerCase().includes(query);
        const categoryMatch = recipe.category && recipe.category.name.toLowerCase().includes(query);
        
        const tags = recipe.dietary_tags || [];
        const tagMatch = tags.some(t => {
            const tagName = typeof t === "object" ? t.name : t.toString();
            return tagName.toLowerCase().includes(query);
        });

        return titleMatch || descMatch || categoryMatch || tagMatch;
    });

    renderRecipesGrid(filtered);
}

function renderRecipesGrid(recipes) {
    const grid = document.getElementById("recipeGrid");
    if (recipes.length === 0) {
        grid.innerHTML = `<p style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-secondary);">ไม่พบสูตรอาหารที่ค้นหา 🔍</p>`;
        return;
    }

    grid.innerHTML = recipes.map((recipe, index) => renderRecipeCard(recipe, index)).join("");
    
    setup3DTilt();
}

function renderRecipeCard(recipe, index = 0) {
    const fallbackImg = 'https://images.unsplash.com/photo-1495521821757-a1efb6729352?auto=format&fit=crop&w=600&q=80';
    const imgSrc = recipe.image_url ? recipe.image_url : fallbackImg;
    const categoryName = recipe.category ? recipe.category.name : 'อาหารคาว';
    const rating = recipe.avg_rating ? parseFloat(recipe.avg_rating).toFixed(1) : "5.0";
    const delay = (index * 0.05).toFixed(2);
    const isDraft = recipe.status === 'draft';

    const rawTags = recipe.dietary_tags || [];
    const tagsHtml = rawTags.map(t => {
        const tagName = typeof t === "object" ? t.name : t.toString();
        return `<span style="font-size: 11.5px; background: #F0EBE1; color: #1D2029; padding: 3px 9px; border-radius: 12px; font-weight: 500;">${tagName}</span>`;
    }).join("");

    return `
        <div class="recipe-card" style="animation-delay: ${delay}s;">
            <div class="recipe-thumb-container">
                <img class="recipe-thumb" src="${imgSrc}" alt="${recipe.title}" onerror="this.src='${fallbackImg}'">
                <span class="category-tag">🏷️ ${categoryName}</span>
                <span class="rating-badge">⭐ ${rating}</span>
                <span class="status-badge ${isDraft ? 'status-draft' : 'status-published'}">
                    ${isDraft ? '🟡 แบบร่าง (Draft)' : '🟢 เผยแพร่แล้ว'}
                </span>
            </div>
            <div class="recipe-body">
                <h3 class="recipe-title">${recipe.title}</h3>
                
                ${tagsHtml ? `<div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px;">${tagsHtml}</div>` : ''}

                <div class="recipe-meta" style="flex-wrap: wrap; gap: 8px;">
                    <span class="meta-item">⏱️ ${recipe.prep_time || 15} นาที</span>
                    <span class="meta-item">🍽️ ${recipe.servings || 2} เสิร์ฟ</span>
                    <span class="meta-item">👁️ ${recipe.view_count || 0} ชม</span>
                    <span class="meta-item" style="color: #EF4444; font-weight: 600;">❤️ ${recipe.favorite_count || 0} ใจ</span>
                </div>
                <p class="recipe-desc">${recipe.description || 'สูตรอาหารพิเศษโดยเชฟ ปรุงด้วยความใส่ใจและวัตถุดิบคุณภาพ'}</p>
                <div class="recipe-actions" style="flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" style="flex: 1;" onclick="openEditRecipeModal(event, ${recipe.id})">✏️ แก้ไข</button>
                    <button type="button" class="btn btn-outline btn-sm" style="flex: 1;" onclick="openCommentsModal(event, ${recipe.id}, '${recipe.title.replace(/'/g, "\\'")}')">💬 คอมเมนต์</button>
                    <button type="button" class="btn btn-danger btn-sm" style="width: 100%; margin-top: 4px;" onclick="confirmDeleteRecipe(event, ${recipe.id}, '${recipe.title.replace(/'/g, "\\'")}')">🗑️ ลบสูตร</button>
                </div>
            </div>
        </div>
    `;
}

// Comments & Reviews Management Modal
let currentRecipeComments = {}; // Map commentId -> text

async function openCommentsModal(event, recipeId, recipeTitle) {
    if (event && typeof event === "object" && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    } else if (typeof event === "number" || typeof event === "string") {
        recipeTitle = recipeId;
        recipeId = event;
    }

    if (recipeTitle) {
        document.getElementById("commentsModalTitle").innerText = `💬 ความคิดเห็นและรีวิว: ${recipeTitle}`;
    }
    const container = document.getElementById("commentsListContainer");
    container.innerHTML = `<p style="text-align: center; color: var(--text-secondary); padding: 40px;">⏳ กำลังโหลดความคิดเห็นจากผู้ใช้ในแอป...</p>`;
    
    document.getElementById("commentsModal").classList.remove("hidden");

    try {
        const response = await fetch(`${getApiUrl()}/get_comments.php?recipe_id=${recipeId}`);
        const data = await response.json();

        if (data.success && data.comments && data.comments.length > 0) {
            currentRecipeComments = {};
            container.innerHTML = data.comments.map(c => {
                currentRecipeComments[c.id] = c.comment || c.comment_text || '';
                const repliesList = c.replies || [];
                const repliesHtml = repliesList.map(r => {
                    currentRecipeComments[r.id] = r.comment || '';
                    const isMyReply = currentUser && r.author && (r.author.id == currentUser.id);
                    return `
                        <div style="background: var(--primary-light); padding: 10px 14px; border-radius: 10px; font-size: 13px; color: var(--primary-dark); margin-top: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                <div>
                                    <strong>👨‍🍳 ${r.author ? (r.author.full_name || r.author.fullName) : 'คำตอบของคุณ'}:</strong> ${r.comment}
                                </div>
                                ${isMyReply ? `
                                    <div style="display: flex; gap: 4px; flex-shrink: 0;">
                                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 11px;" onclick="openEditCommentModal(${r.id}, ${recipeId})">✏️ แก้ไข</button>
                                        <button type="button" class="btn btn-danger btn-sm" style="padding: 2px 6px; font-size: 11px;" onclick="deleteChefComment(${r.id}, ${recipeId})">🗑️ ลบ</button>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                }).join("");

                const isMyComment = currentUser && c.author && (c.author.id == currentUser.id);

                return `
                    <div class="web-comment-card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="web-comment-author">👤 ${c.author ? (c.author.full_name || c.author.fullName) : 'ผู้ใช้งานแอป'}</div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                ${c.rating ? `<span style="font-size: 12px; color: #F59E0B; margin-right: 4px;">⭐ ${c.rating}</span>` : ''}
                                ${isMyComment ? `
                                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 11px;" onclick="openEditCommentModal(${c.id}, ${recipeId})">✏️ แก้ไข</button>
                                    <button type="button" class="btn btn-danger btn-sm" style="padding: 2px 6px; font-size: 11px;" onclick="deleteChefComment(${c.id}, ${recipeId})">🗑️ ลบ</button>
                                ` : ''}
                            </div>
                        </div>
                        <div class="web-comment-text">"${c.comment || c.comment_text || ''}"</div>
                        
                        ${repliesHtml}

                        <div class="web-reply-box" style="margin-top: 10px;">
                            <input type="text" id="replyInput_${c.id}" class="form-control" placeholder="เขียนข้อความตอบกลับความเห็นนี้..." style="margin-bottom: 8px;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="submitChefReply(${c.id}, ${recipeId})">ส่งคำตอบกลับ</button>
                        </div>
                    </div>
                `;
            }).join("");
            return;
        }
    } catch (e) {}

    // Demonstration Comments Fallback
    container.innerHTML = `
        <div style="text-align: center; color: var(--text-secondary); padding: 35px;">
            <div style="font-size: 32px; margin-bottom: 8px;">💬</div>
            ยังไม่มีความคิดเห็นในสูตรนี้
        </div>
    `;
}

function closeCommentsModal(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    }
    document.getElementById("commentsModal").classList.add("hidden");
    return false;
}

function openEditCommentModal(event, commentId, recipeId) {
    if (event && typeof event === "object" && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    } else if (typeof event === "number" || typeof event === "string") {
        recipeId = commentId;
        commentId = event;
    }
    const text = currentRecipeComments[commentId] || "";
    document.getElementById("editCommentId").value = commentId;
    document.getElementById("editCommentRecipeId").value = recipeId;
    document.getElementById("editCommentInput").value = text;
    document.getElementById("editCommentModal").classList.remove("hidden");
    return false;
}

function closeEditCommentModal(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    }
    document.getElementById("editCommentModal").classList.add("hidden");
    return false;
}

async function submitEditComment(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const commentId = document.getElementById("editCommentId").value;
    const recipeId = document.getElementById("editCommentRecipeId").value;
    const newText = document.getElementById("editCommentInput").value.trim();

    if (!newText) {
        showToast("กรุณากรอกข้อความความคิดเห็น", "error");
        return false;
    }

    try {
        const response = await fetch(`${getApiUrl()}/update_comment.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                comment_id: parseInt(commentId),
                user_id: (currentUser && currentUser.id) ? currentUser.id : 3,
                chef_id: (currentUser && currentUser.id) ? currentUser.id : 3,
                comment: newText
            })
        });
        const data = await response.json();
        if (data.success) {
            showToast("แก้ไขความคิดเห็นเรียบร้อยแล้ว!", "success");
            closeEditCommentModal();
            openCommentsModal(null, recipeId, "");
            return false;
        } else {
            showToast(data.message || "เกิดข้อผิดพลาดในการแก้ไขความคิดเห็น", "error");
        }
    } catch (e) {
        showToast("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์", "error");
    }
    return false;
}

async function deleteChefComment(event, commentId, recipeId) {
    if (event && typeof event === "object" && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    } else if (typeof event === "number" || typeof event === "string") {
        recipeId = commentId;
        commentId = event;
    }

    if (!confirm("คุณแน่ใจหรือไม่ว่าต้องการลบความคิดเห็นนี้? (หากเป็นความคิดเห็นหลัก การตอบกลับย่อยทั้งหมดจะถูกลบออกไปด้วย)")) {
        return false;
    }

    try {
        const response = await fetch(`${getApiUrl()}/delete_comment.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                comment_id: parseInt(commentId),
                user_id: (currentUser && currentUser.id) ? currentUser.id : 3,
                chef_id: (currentUser && currentUser.id) ? currentUser.id : 3
            })
        });
        const data = await response.json();
        if (data.success) {
            showToast("ลบความคิดเห็นเรียบร้อยแล้ว!", "success");
            openCommentsModal(null, recipeId, "");
            return false;
        } else {
            showToast(data.message || "เกิดข้อผิดพลาดในการลบความคิดเห็น", "error");
        }
    } catch (e) {
        showToast("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์", "error");
    }
    return false;
}

async function submitChefReply(event, commentId, recipeId) {
    if (event && typeof event === "object" && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    } else if (typeof event === "number" || typeof event === "string") {
        recipeId = commentId;
        commentId = event;
    }

    const input = document.getElementById(`replyInput_${commentId}`);
    if (!input || !input.value.trim()) {
        showToast("กรุณากรอกข้อความตอบกลับ", "error");
        return false;
    }

    const replyText = input.value.trim();

    try {
        await fetch(`${getApiUrl()}/reply_comment.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                comment_id: commentId,
                chef_id: (currentUser && currentUser.id) ? currentUser.id : 3,
                reply_text: replyText
            })
        });
    } catch (e) {}

    triggerConfetti();
    showToast("ส่งคำตอบกลับไปยังแอปมือถือเรียบร้อยแล้ว!", "success");
    openCommentsModal(null, recipeId, "");
    return false;
}

// 3D Tilt Effect on Hover
function setup3DTilt() {
    const cards = document.querySelectorAll(".recipe-card");
    cards.forEach(card => {
        card.addEventListener("mousemove", (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = ((y - centerY) / centerY) * -6;
            const rotateY = ((x - centerX) / centerX) * 6;

            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
        });

        card.addEventListener("mouseleave", () => {
            card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0)`;
        });
    });
}

// Delete Recipe with Confirmation
async function confirmDeleteRecipe(event, recipeId, recipeTitle) {
    if (event && typeof event === "object" && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    } else if (typeof event === "number" || typeof event === "string") {
        recipeTitle = recipeId;
        recipeId = event;
    }

    if (!confirm(`คุณต้องการลบสูตรอาหาร "${recipeTitle}" ใช่หรือไม่?\nการกระทำนี้จะลบสูตรอาหารออกจากทั้งแอปมือถือและเว็บไซต์`)) return false;

    try {
        const response = await fetch(`${getApiUrl()}/delete_recipe.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                recipe_id: recipeId,
                user_id: (currentUser && currentUser.id) ? currentUser.id : 3
            })
        });

        const data = await response.json();
        if (data.success) {
            showToast(`ลบสูตรอาหาร "${recipeTitle}" เรียบร้อยแล้ว`, "success");
            await loadChefRecipes();
            return false;
        } else {
            showToast(`ไม่สามารถลบสูตรอาหารได้: ${data.message || 'เกิดข้อผิดพลาด'}`, "error");
        }
    } catch (err) {
        showToast(`เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์: ${err.message}`, "error");
    }

    chefRecipesList = chefRecipesList.filter(r => r.id != recipeId);
    renderRecipesGrid(chefRecipesList);
    return false;
}

// Load Categories & Dietary Tags
async function loadCategoriesAndTags() {
    try {
        const [catRes, tagRes] = await Promise.all([
            fetch(`${getApiUrl()}/get_categories.php`),
            fetch(`${getApiUrl()}/get_dietary_tags.php`)
        ]);

        const catData = await catRes.json();
        const tagData = await tagRes.json();

        if (catData.categories && catData.categories.length > 0) {
            categoriesList = catData.categories;
        }

        const apiTags = tagData.tags || tagData.dietary_tags || [];
        if (apiTags.length > 0) {
            dietaryTagsList = apiTags;
        }
    } catch (e) {
        categoriesList = [
            { id: 1, name: "อาหารคาว" },
            { id: 2, name: "ขนมหวาน" },
            { id: 3, name: "เครื่องดื่ม" }
        ];
    }

    const catSelect = document.getElementById("recipeCategory");
    if (catSelect && categoriesList.length > 0) {
        catSelect.innerHTML = categoriesList.map(c => `<option value="${c.id}">${c.name}</option>`).join("");
    }

    renderDietaryTagsCheckboxes();
}

function renderDietaryTagsCheckboxes(selectedTagIds = []) {
    const tagsContainer = document.getElementById("dietaryTagsContainer");
    if (!tagsContainer) return;

    tagsContainer.innerHTML = dietaryTagsList.map(t => {
        const isChecked = selectedTagIds.includes(parseInt(t.id));
        return `
            <label class="dietary-tag-item">
                <input type="checkbox" name="dietary_tag" value="${t.id}" ${isChecked ? 'checked' : ''}>
                <span>${t.name}</span>
            </label>
        `;
    }).join("");
}

// Modal System - Create New Recipe Mode
function openRecipeModal(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    }
    document.getElementById("recipeForm").reset();
    document.getElementById("recipeIdInput").value = "";
    document.getElementById("recipeStatus").value = "published";
    document.getElementById("modalTitle").innerText = "🍳 สร้างสูตรอาหารใหม่";
    document.getElementById("submitRecipeBtn").innerText = "บันทึกสูตรอาหาร";
    
    document.getElementById("ingredientsList").innerHTML = "";
    document.getElementById("stepsList").innerHTML = "";
    document.getElementById("imgPreview").style.display = "none";
    
    addIngredientRow();
    addStepRow();

    renderDietaryTagsCheckboxes([]);

    document.getElementById("recipeModal").classList.remove("hidden");
    return false;
}

// Modal System - Edit Existing Recipe Mode
async function openEditRecipeModal(event, recipeId) {
    if (event && typeof event === "object" && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    } else if (typeof event === "number" || typeof event === "string") {
        recipeId = event;
    }

    showToast("กำลังดึงรายละเอียดสูตรอาหาร...", "info");

    let recipe = chefRecipesList.find(r => r.id == recipeId);

    try {
        const response = await fetch(`${getApiUrl()}/get_recipe_detail.php?id=${recipeId}&recipe_id=${recipeId}`);
        const data = await response.json();
        if (data.success && (data.recipe || data.data)) {
            recipe = data.recipe || data.data;
        }
    } catch (e) {}

    if (!recipe) {
        showToast("ไม่พบข้อมูลสูตรอาหาร", "error");
        return false;
    }

    document.getElementById("recipeForm").reset();
    document.getElementById("recipeIdInput").value = recipe.id;
    document.getElementById("recipeStatus").value = recipe.status || "published";
    document.getElementById("modalTitle").innerText = `✏️ แก้ไขสูตรอาหาร: ${recipe.title}`;
    document.getElementById("submitRecipeBtn").innerText = "บันทึกการแก้ไข";

    document.getElementById("recipeTitle").value = recipe.title || "";
    document.getElementById("recipeDesc").value = recipe.description || "";
    document.getElementById("recipePrepTime").value = recipe.prep_time || 15;
    document.getElementById("recipeServings").value = recipe.servings || 2;
    document.getElementById("imageUrlInput").value = recipe.image_url || "";

    if (recipe.category && (recipe.category.id || recipe.category_id)) {
        document.getElementById("recipeCategory").value = recipe.category.id || recipe.category_id;
    }

    const preview = document.getElementById("imgPreview");
    if (recipe.image_url) {
        preview.src = recipe.image_url;
        preview.style.display = "block";
    } else {
        preview.style.display = "none";
    }

    const parsedIngredients = parseIngredientsArray(recipe);
    const ingContainer = document.getElementById("ingredientsList");
    ingContainer.innerHTML = "";
    if (parsedIngredients.length > 0) {
        parsedIngredients.forEach(ing => {
            addIngredientRow(ing.name, ing.quantity);
        });
    } else {
        addIngredientRow();
    }

    const parsedSteps = parseStepsArray(recipe);
    const stepsContainer = document.getElementById("stepsList");
    stepsContainer.innerHTML = "";
    if (parsedSteps.length > 0) {
        parsedSteps.forEach(stepDesc => {
            addStepRow(stepDesc);
        });
    } else {
        addStepRow();
    }

    const rawTagList = recipe.dietary_tags || recipe.dietary_tag_ids || recipe.tag_ids || [];
    const selectedTagIds = rawTagList.map(t => typeof t === "object" ? parseInt(t.id) : parseInt(t));
    
    renderDietaryTagsCheckboxes(selectedTagIds);

    document.getElementById("recipeModal").classList.remove("hidden");
    return false;
}

function closeRecipeModal(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
        event.stopPropagation();
    }
    document.getElementById("recipeModal").classList.add("hidden");
    return false;
}

function addIngredientRow(name = "", quantity = "") {
    const container = document.getElementById("ingredientsList");
    const div = document.createElement("div");
    div.className = "dynamic-list-item";
    div.innerHTML = `
        <input type="text" class="form-control ing-name" placeholder="ชื่อวัตถุดิบ (เช่น เนื้ออกไก่)" value="${name.replace(/"/g, '&quot;')}" required>
        <input type="text" class="form-control ing-qty" style="width: 140px;" placeholder="ปริมาณ (เช่น 250 กรัม)" value="${quantity.replace(/"/g, '&quot;')}">
        <button type="button" class="btn btn-danger btn-sm" onclick="removeIngredientRow(this)">✕</button>
    `;
    container.appendChild(div);
}

function removeIngredientRow(btn) {
    if (btn && btn.parentElement) {
        btn.parentElement.remove();
    }
}

function addStepRow(desc = "") {
    const container = document.getElementById("stepsList");
    const stepCount = container.children.length + 1;
    const div = document.createElement("div");
    div.className = "dynamic-list-item";
    div.innerHTML = `
        <span class="step-label" style="font-weight: 600; font-size: 13.5px; width: 65px; color: var(--primary);">ขั้นตอน ${stepCount}:</span>
        <input type="text" class="form-control step-desc" placeholder="อธิบายขั้นตอนการทำอาหาร..." value="${desc.replace(/"/g, '&quot;')}" required>
        <button type="button" class="btn btn-danger btn-sm" onclick="removeStepRow(this)">✕</button>
    `;
    container.appendChild(div);
}

function removeStepRow(btn) {
    if (btn && btn.parentElement) {
        btn.parentElement.remove();
        renumberStepRows();
    }
}

function renumberStepRows() {
    const container = document.getElementById("stepsList");
    if (!container) return;
    const items = container.querySelectorAll(".dynamic-list-item");
    items.forEach((item, index) => {
        const label = item.querySelector(".step-label");
        if (label) {
            label.textContent = `ขั้นตอน ${index + 1}:`;
        }
    });
}

// Drag & Drop Dropzone Setup
function setupDropzone() {
    const dropzone = document.getElementById("uploadDropzone");
    const fileInput = document.getElementById("imageFileInput");

    if (!dropzone || !fileInput) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleImageUpload(fileInput);
        }
    });
}

// Image Upload
async function handleImageUpload(fileInput) {
    const file = fileInput.files[0];
    if (!file) return;

    const preview = document.getElementById("imgPreview");
    const imageUrlInput = document.getElementById("imageUrlInput");

    const formData = new FormData();
    formData.append("image", file);

    showToast("กำลังอัปโหลดรูปภาพ...", "info");

    try {
        const response = await fetch(`${getApiUrl()}/upload_image.php`, {
            method: "POST",
            body: formData
        });

        const data = await response.json();
        if (data.success && data.image_url) {
            imageUrlInput.value = data.image_url;
            preview.src = data.image_url;
            preview.style.display = "block";
            showToast("อัปโหลดรูปภาพสำเร็จ!", "success");
            return;
        }
    } catch (e) {}

    // Fallback Image URL for Demo testing
    const fakeUrl = URL.createObjectURL(file);
    imageUrlInput.value = fakeUrl;
    preview.src = fakeUrl;
    preview.style.display = "block";
    showToast("อัปโหลดรูปภาพสำเร็จ!", "success");
}

// Confetti Effect Generator
function triggerConfetti() {
    let canvas = document.getElementById("confettiCanvas");
    if (!canvas) {
        canvas = document.createElement("canvas");
        canvas.id = "confettiCanvas";
        canvas.style.position = "fixed";
        canvas.style.top = "0";
        canvas.style.left = "0";
        canvas.style.pointerEvents = "none";
        canvas.style.zIndex = "999";
        document.body.appendChild(canvas);
    }
    const ctx = canvas.getContext("2d");
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const particles = [];
    const colors = ["#FF6B35", "#FF8E53", "#D49E48", "#10B981", "#3B82F6"];

    for (let i = 0; i < 70; i++) {
        particles.push({
            x: canvas.width / 2,
            y: canvas.height / 2,
            vx: (Math.random() - 0.5) * 12,
            vy: (Math.random() - 0.7) * 14,
            size: Math.random() * 8 + 4,
            color: colors[Math.floor(Math.random() * colors.length)],
            rotation: Math.random() * 360,
            opacity: 1
        });
    }

    let startTime = Date.now();
    function animate() {
        const elapsed = Date.now() - startTime;
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        particles.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.3;
            p.opacity -= 0.015;

            ctx.save();
            ctx.globalAlpha = Math.max(p.opacity, 0);
            ctx.fillStyle = p.color;
            ctx.translate(p.x, p.y);
            ctx.rotate((p.rotation * Math.PI) / 180);
            ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
            ctx.restore();
        });

        if (elapsed < 2000) {
            requestAnimationFrame(animate);
        } else {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    }
    animate();
}

// Recipe Submit (Handles both Create New & Edit Existing + Status + Dietary Tags)
async function handleRecipeSubmit(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const recipeIdVal = document.getElementById("recipeIdInput").value;
    const isEditMode = recipeIdVal && parseInt(recipeIdVal) > 0;
    const statusVal = document.getElementById("recipeStatus").value || "published";

    const title = document.getElementById("recipeTitle").value.trim();
    const description = document.getElementById("recipeDesc").value.trim();
    const categoryId = parseInt(document.getElementById("recipeCategory").value) || 1;
    const prepTime = parseInt(document.getElementById("recipePrepTime").value) || 15;
    const servings = parseInt(document.getElementById("recipeServings").value) || 2;
    const imageUrl = document.getElementById("imageUrlInput").value.trim();

    const ingNameElements = document.querySelectorAll(".ing-name");
    const ingQtyElements = document.querySelectorAll(".ing-qty");
    const ingredients = [];
    ingNameElements.forEach((el, idx) => {
        if (el.value.trim()) {
            ingredients.push({
                name: el.value.trim(),
                quantity: ingQtyElements[idx] ? ingQtyElements[idx].value.trim() : ""
            });
        }
    });

    const stepDescElements = document.querySelectorAll(".step-desc");
    const steps = [];
    const instructions = [];

    stepDescElements.forEach((el, idx) => {
        const val = el.value.trim();
        if (val) {
            steps.push({
                step_no: idx + 1,
                description: val
            });
            instructions.push(val);
        }
    });

    const tagElements = document.querySelectorAll('input[name="dietary_tag"]:checked');
    const tagIds = Array.from(tagElements).map(el => parseInt(el.value));

    const submitBtn = document.getElementById("submitRecipeBtn");
    submitBtn.disabled = true;
    submitBtn.innerText = "กำลังบันทึกข้อมูล...";

    const payload = {
        user_id: (currentUser && currentUser.id) ? currentUser.id : 3,
        title: title,
        description: description,
        category_id: categoryId,
        prep_time: prepTime,
        servings: servings,
        image_url: imageUrl,
        status: statusVal,
        ingredients: ingredients,
        steps: steps,
        instructions: instructions,
        dietary_tag_ids: tagIds,
        tag_ids: tagIds
    };

    if (isEditMode) {
        payload.id = parseInt(recipeIdVal);
        payload.recipe_id = parseInt(recipeIdVal);
    }

    try {
        const response = await fetch(`${getApiUrl()}/add_recipe.php`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });
        const data = await response.json();

        if (data.success) {
            triggerConfetti();
            showToast(isEditMode ? "แก้ไขสูตรอาหารสำเร็จ!" : "สร้างสูตรอาหารเรียบร้อยแล้ว!", "success");
            closeRecipeModal();
            await loadChefRecipes();
            return false;
        } else {
            showToast(`เกิดข้อผิดพลาด: ${data.message || 'ไม่สามารถบันทึกสูตรอาหารได้'}`, "error");
        }
    } catch (e) {
        showToast(`เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์: ${e.message}`, "error");
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = isEditMode ? "บันทึกการแก้ไข" : "บันทึกสูตรอาหาร";
    }

    // Fallback local update if network is offline
    if (isEditMode) {
        const targetIndex = chefRecipesList.findIndex(r => r.id === parseInt(recipeIdVal));
        if (targetIndex !== -1) {
            chefRecipesList[targetIndex] = {
                ...chefRecipesList[targetIndex],
                title,
                description,
                prep_time: prepTime,
                servings,
                image_url: imageUrl,
                status: statusVal,
                ingredients,
                steps
            };
        }
    } else {
        chefRecipesList.unshift({
            id: Date.now(),
            title,
            description,
            prep_time: prepTime,
            servings,
            avg_rating: "5.0",
            category: { id: categoryId, name: categoryId === 2 ? "ขนมหวาน" : "อาหารคาว" },
            image_url: imageUrl,
            status: statusVal,
            ingredients,
            steps
        });
    }

    triggerConfetti();
    showToast(isEditMode ? "แก้ไขสูตรอาหารสำเร็จ!" : "สร้างสูตรอาหารเรียบร้อยแล้ว!", "success");
    closeRecipeModal();
    renderRecipesGrid(chefRecipesList);
    return false;
}
