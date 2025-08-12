document.getElementById('createElectionForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent normal form submission

    let formData = new FormData(this);

    fetch('create_election.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message); // "localhost says" style alert
            location.reload();   // reload current page
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Something went wrong.");
    });
});
