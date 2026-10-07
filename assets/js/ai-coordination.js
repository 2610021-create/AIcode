/**
 * AIコーディネート機能 制御スクリプト (assets/js/ai-coordination.js)
 * 
 * 主な機能:
 * 1. 条件選択の最大3つ制限（3つ選択されたら残りの未選択セレクトボックスをdisabledにする）
 * 2. 選択解除で自動的に再度活性化
 * 3. リアルタイムカウンターバッジ・アラートメッセージ更新
 * 4. リセットボタン & 条件チップの削除
 */

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('ai-condition-form');
  if (!form) return;

  const selects = form.querySelectorAll('.condition-select');
  const counterBadge = document.getElementById('condition-count-badge');
  const limitAlert = document.getElementById('condition-limit-alert');
  const btnReset = document.getElementById('btn-condition-reset');
  const MAX_CONDITIONS = 3;

  /**
   * 条件選択の選択数をチェックし、3つに達した場合は未選択要素を無効化する
   */
  function applyConditionLimit() {
    let selectedCount = 0;

    selects.forEach((sel) => {
      if (sel.value.trim() !== '') {
        selectedCount++;
        sel.classList.add('has-value');
      } else {
        sel.classList.remove('has-value');
      }
    });

    // カウンターバッジの更新
    if (counterBadge) {
      counterBadge.textContent = `${selectedCount} / ${MAX_CONDITIONS} 選択中`;
      if (selectedCount >= MAX_CONDITIONS) {
        counterBadge.classList.add('limit-reached');
      } else {
        counterBadge.classList.remove('limit-reached');
      }
    }

    // 3つ以上選択された場合：未選択のセレクトボックスを disabled にする
    if (selectedCount >= MAX_CONDITIONS) {
      selects.forEach((sel) => {
        const wrapper = sel.closest('.condition-field');
        if (sel.value.trim() === '') {
          sel.disabled = true;
          if (wrapper) {
            wrapper.classList.add('is-disabled');
            wrapper.setAttribute('title', '条件は最大3つまでです。他の条件を選択するには選択中の項目を解除してください。');
          }
        } else {
          sel.disabled = false;
          if (wrapper) {
            wrapper.classList.remove('is-disabled');
            wrapper.removeAttribute('title');
          }
        }
      });

      if (limitAlert) {
        limitAlert.style.display = 'inline-flex';
      }
    } else {
      // 3つ未満の場合：すべて有効化
      selects.forEach((sel) => {
        sel.disabled = false;
        const wrapper = sel.closest('.condition-field');
        if (wrapper) {
          wrapper.classList.remove('is-disabled');
          wrapper.removeAttribute('title');
        }
      });

      if (limitAlert) {
        limitAlert.style.display = 'none';
      }
    }
  }

  // 初期化実行（ページ読み込み時のURLクエリパラメータ反映）
  applyConditionLimit();

  // 各セレクトボックスの変更イベント
  selects.forEach((sel) => {
    sel.addEventListener('change', () => {
      applyConditionLimit();
    });
  });

  // フォーム送信前の処理（disabledの入力も送信できるように一時解除、または空文字として処理）
  form.addEventListener('submit', () => {
    selects.forEach((sel) => {
      sel.disabled = false;
    });
  });

  // リセットボタン押下時
  if (btnReset) {
    btnReset.addEventListener('click', (e) => {
      e.preventDefault();
      selects.forEach((sel) => {
        sel.value = '';
      });
      applyConditionLimit();
      form.submit();
    });
  }

  // 条件チップ（タグ）の削除ボタン
  const removeChips = document.querySelectorAll('.remove-chip-btn');
  removeChips.forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const targetName = btn.getAttribute('data-target');
      if (targetName) {
        const targetSelect = form.querySelector(`[name="${targetName}"]`);
        if (targetSelect) {
          targetSelect.value = '';
          applyConditionLimit();
          form.submit();
        }
      }
    });
  });

  // お気に入りボタン（ハート）のクリック処理（視覚フィードバック）
  const favButtons = document.querySelectorAll('.card-fav-btn, .favorite-btn');
  favButtons.forEach((btn) => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.classList.toggle('active');
      const icon = this.querySelector('i');
      if (icon) {
        if (this.classList.contains('active')) {
          icon.classList.remove('fa-regular');
          icon.classList.add('fa-solid');
        } else {
          icon.classList.remove('fa-solid');
          icon.classList.add('fa-regular');
        }
      }
    });
  });
});
