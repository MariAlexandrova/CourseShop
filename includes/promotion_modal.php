<!-- Модальное окно добавления акции -->
<div class="modal fade" id="addPromotionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Новая акция</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Скидка (%) *</label>
                        <input type="number" name="discount" min="1" max="99" step="0.1" 
                               class="form-control" required>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Начало *</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Окончание *</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="add_promotion" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования акции -->
<div class="modal fade" id="editPromotionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="promotion_id" id="edit_promotion_id">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Редактирование акции</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название *</label>
                        <input type="text" name="name" id="edit_promotion_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Скидка (%) *</label>
                        <input type="number" name="discount" id="edit_promotion_discount" 
                               min="1" max="99" step="0.1" class="form-control" required>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Начало *</label>
                            <input type="date" name="start_date" id="edit_promotion_start" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Окончание *</label>
                            <input type="date" name="end_date" id="edit_promotion_end" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" name="edit_promotion" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>