// Refund calculation logic
document.getElementById('supplierRefundPenalty').addEventListener('input', updateRefundAmount);
document.getElementById('serviceRefundPenalty').addEventListener('input', updateRefundAmount);
document.getElementById('refundBase').addEventListener('input', updateRefundAmount);

function updateRefundAmount() {
    const base = parseFloat(document.getElementById('refundBase').value) || 0;
    const supplierPenalty = parseFloat(document.getElementById('supplierRefundPenalty').value) || 0;
    const servicePenalty = parseFloat(document.getElementById('serviceRefundPenalty').value) || 0;

    // Total penalty
    const totalPenalty = supplierPenalty + servicePenalty;

    // Refund amount calculation
    const refundAmount = base - supplierPenalty - servicePenalty;

    // Show refund amount in readonly input
    document.getElementById('refundAmount').value = refundAmount > 0 ? refundAmount : 0;
} 