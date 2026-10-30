
        let actionProcessing = false;

        function confirmAction(form, message) {
            if (actionProcessing || form.dataset.processing === "1") return false;
            if (!confirm(message)) return false;
            actionProcessing = true;
            form.dataset.processing = "1";
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.style.pointerEvents = "none";
                button.style.opacity = ".5";
            }
            return true;
        }

        function openPassengerModal(p) {
            const modal = document.getElementById("passengerModal");
            const image = document.getElementById("modalImage");
            if (p.profile_image && p.profile_image.trim()) {
                image.src = "../uploads/profile/passenger/" + p.name + "/" + p.profile_image.trim();
            } else {
                image.src = "../uploads/default.png";
            }
            image.onerror = function() {
                this.onerror = null;
                this.src = "../uploads/default.png";
            };
            document.getElementById("modalName").textContent = p.name || "-";
            document.getElementById("modalEmail").textContent = p.email || "-";
            document.getElementById("modalId").textContent = p.user_id || "-";
            document.getElementById("modalPhone").textContent = p.phone || "-";
            document.getElementById("modalVerification").textContent = p.verification_status === "verified" ? "Verified" : p.verification_status === "rejected" ? "Rejected" : "Pending";
            document.getElementById("modalBookings").textContent = (p.total_bookings || 0) + " Booking(s)";
            if (p.created_at) {
                const d = new Date(p.created_at.replace(" ", "T"));
                document.getElementById("modalDate").textContent = !isNaN(d) ? d.toLocaleDateString("en-GB", {
                    day: "2-digit",
                    month: "short",
                    year: "numeric"
                }) : p.created_at;
            } else {
                document.getElementById("modalDate").textContent = "-";
            }
            const pending = document.getElementById("modalPending");
            const approve = document.getElementById("modalApprove");
            const reject = document.getElementById("modalReject");
            document.getElementById("modalPendingId").value = p.user_id;
            document.getElementById("modalApproveId").value = p.user_id;
            document.getElementById("modalRejectId").value = p.user_id;
            document.getElementById("modalDeleteId").value = p.user_id;
            pending.className = "action-btn pending";
            approve.className = "action-btn approve";
            reject.className = "action-btn reject";
            pending.disabled = false;
            approve.disabled = false;
            reject.disabled = false;
            pending.innerHTML = '<i class="fa-solid fa-clock"></i>&nbsp; Pending ';
            approve.innerHTML = '<i class="fa-solid fa-check"></i>&nbsp; Approve ';
            reject.innerHTML = '<i class="fa-solid fa-xmark"></i>&nbsp; Reject ';;
            if (p.verification_status === "pending") {
                pending.classList.add("current", "disabled");
                pending.disabled = true;
            } else if (p.verification_status === "verified") {
                approve.classList.add("current", "disabled");
                approve.disabled = true;
            } else if (p.verification_status === "rejected") {
                reject.classList.add("current", "disabled");
                reject.disabled = true;
            }
            document.getElementById("modalDetails").href = "view_passenger.php?id=" + p.user_id;
            document.getElementById("modalEdit").href = "edit_passenger.php?id=" + p.user_id;
            modal.classList.add("active");
        }

        function closePassengerModal() {
            document.getElementById("passengerModal").classList.remove("active");
        }

        function closeModalOutside(e) {
            if (e.target === document.getElementById("passengerModal")) closePassengerModal();
        }
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closePassengerModal();
        });
