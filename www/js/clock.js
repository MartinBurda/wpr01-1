
    function drawClock() {
        const canvas = document.getElementById('clock');
        const ctx = canvas.getContext('2d');
        const radius = canvas.width / 2;
        const clockRadius = radius * 0.90;

        function drawFace() {
            ctx.beginPath();
            ctx.arc(0, 0, clockRadius, 0, 2 * Math.PI);
            ctx.fillStyle = 'white';
            ctx.fill();
            ctx.strokeStyle = 'black';
            ctx.lineWidth = radius * 0.05;
            ctx.stroke();

            // Draw hour markers
            ctx.lineWidth = radius * 0.02;
            for (let i = 0; i < 12; i++) {
                const angle = i * Math.PI / 6;
                ctx.beginPath();
                ctx.save(); // Save context
                ctx.rotate(angle);
                ctx.moveTo(0, -clockRadius * 0.85);
                ctx.lineTo(0, -clockRadius * 0.95);
                ctx.stroke();
                ctx.restore(); // Restore context
            }
        }

        function drawHands() {
            const now = new Date();
            let hour = now.getHours();
            const minute = now.getMinutes();
            const second = now.getSeconds();

            // Hour hand
            hour = hour % 12;
            const hourAngle = (hour * Math.PI / 6) + (minute * Math.PI / (6 * 60));
            ctx.save();
            ctx.rotate(hourAngle);
            ctx.beginPath();
            ctx.lineWidth = radius * 0.06;
            ctx.strokeStyle = 'black';
            ctx.moveTo(0, 0);
            ctx.lineTo(0, -clockRadius * 0.5);
            ctx.stroke();
            ctx.restore();

            // Minute hand
            const minuteAngle = (minute * Math.PI / 30);
            ctx.save();
            ctx.rotate(minuteAngle);
            ctx.beginPath();
            ctx.lineWidth = radius * 0.04;
            ctx.strokeStyle = 'black';
            ctx.moveTo(0, 0);
            ctx.lineTo(0, -clockRadius * 0.7);
            ctx.stroke();
            ctx.restore();

            // Second hand
            const secondAngle = (second * Math.PI / 30);
            ctx.save();
            ctx.rotate(secondAngle);
            ctx.beginPath();
            ctx.lineWidth = radius * 0.02;
            ctx.strokeStyle = 'red';
            ctx.moveTo(0, 0);
            ctx.lineTo(0, -clockRadius * 0.8);
            ctx.stroke();
            ctx.restore();
        }

        function updateClock() {
            // Reset the transformation matrix and reapply the translation
            ctx.resetTransform();
            ctx.translate(radius, radius);
            ctx.clearRect(-radius, -radius, canvas.width, canvas.height);
            drawFace();
            drawHands();
        }

        updateClock();
        setInterval(updateClock, 1000);
    }

    document.addEventListener('DOMContentLoaded', drawClock);
