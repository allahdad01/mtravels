document.addEventListener('DOMContentLoaded', () => {
    const baseInput = document.getElementById('base');
    const soldInput = document.getElementById('sold');
    const proInput = document.getElementById('pro');

    // Function to calculate and update the profit field
    function calculatePro() {
        const base = parseFloat(baseInput.value) || 0; // Default to 0 if not valid
        const sold = parseFloat(soldInput.value) || 0; // Default to 0 if not valid
        const pro = sold - base; // Calculate profit

        console.log("Base: ", base);
        console.log("Sold: ", sold);
        console.log('Profit Calculated:', pro);

        // Update the profit field and make sure it's also visible
        proInput.value = pro.toFixed(2);  // Update to two decimal points
        console.log('Updated Profit Input Value: ', proInput.value); // Check updated value
    }

    // Add event listeners for real-time calculation
    baseInput.addEventListener('input', calculatePro);
    soldInput.addEventListener('input', calculatePro);
});