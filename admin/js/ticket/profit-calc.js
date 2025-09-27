document.addEventListener('DOMContentLoaded', () => {
    const baseInput = document.getElementById('base');
    const soldInput = document.getElementById('sold');
    const proInput = document.getElementById('pro');

    // Function to calculate and update the profit field
    function calculatePro() {
        const base = parseFloat(baseInput.value) || 0; // Default to 0 if not valid
        const sold = parseFloat(soldInput.value) || 0; // Default to 0 if not valid
        const discount = parseFloat(document.getElementById('discount').value) || 0; // Default to 0 if not valid
        const pro = sold - discount - base; // Calculate profit with discount

        console.log("Base: ", base);
        console.log("Sold: ", sold);
        console.log("Discount: ", discount);
        console.log('Profit Calculated:', pro);

        // Update the profit field and make sure it's also visible
        proInput.value = pro.toFixed(2);  // Update to two decimal points
        console.log('Updated Profit Input Value: ', proInput.value); // Check updated value
    }

    // Add event listeners for real-time calculation
    baseInput.addEventListener('input', calculatePro);
    soldInput.addEventListener('input', calculatePro);
    document.getElementById('discount').addEventListener('input', calculatePro);
});

document.addEventListener('DOMContentLoaded', () => {
    const editBaseInput = document.getElementById('editBase');
    const editSoldInput = document.getElementById('editSold');
    const editProInput = document.getElementById('editPro');

    // Function to calculate and update the profit field
    function calculateEditPro() {
        const editBase = parseFloat(editBaseInput.value) || 0; // Default to 0 if not valid
        const editSold = parseFloat(editSoldInput.value) || 0; // Default to 0 if not valid
        const editDiscount = parseFloat(document.getElementById('editDiscount').value) || 0; // Default to 0 if not valid
        const editPro = editSold - editDiscount - editBase; // Calculate profit with discount

        console.log("editBase: ", editBase);
        console.log("editSold: ", editSold);
        console.log("editDiscount: ", editDiscount);
        console.log('Profit Calculated:', editPro);

        // Update the profit field and make sure it's also visible
        editProInput.value = editPro.toFixed(2);  // Update to two decimal points
        console.log('Updated Profit Input Value: ', editProInput.value); // Check updated value
    }

    // Add event listeners for real-time calculation
    editBaseInput.addEventListener('input', calculateEditPro);
    editSoldInput.addEventListener('input', calculateEditPro);
    document.getElementById('editDiscount').addEventListener('input', calculateEditPro);
}); 