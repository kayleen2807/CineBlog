(function() {
  const canvas = document.getElementById('cineBg');
  const ctx = canvas.getContext('2d');

  function resize() {
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
    draw();
  }

  function drawIcon(ctx, type, x, y, size, angle) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle);
    ctx.strokeStyle = '#3b82f6';
    ctx.fillStyle   = '#3b82f6';
    ctx.lineWidth   = size * 0.06;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';

    const s = size;

//fondo
    switch(type) {

      case 'popcorn': {
        
        ctx.beginPath();
        ctx.moveTo(-s*0.32, -s*0.05);
        ctx.lineTo(-s*0.38, s*0.48);
        ctx.lineTo( s*0.38, s*0.48);
        ctx.lineTo( s*0.32, -s*0.05);
        ctx.closePath();
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(-s*0.1, -s*0.05);
        ctx.lineTo(-s*0.13, s*0.48);
        ctx.moveTo( s*0.1, -s*0.05);
        ctx.lineTo( s*0.13, s*0.48);
        ctx.stroke();
        
        ctx.lineWidth = size * 0.05;
        const pops = [
          [-s*0.28, -s*0.26, s*0.17],
          [ s*0.0,  -s*0.35, s*0.17],
          [ s*0.28, -s*0.26, s*0.17],
          [-s*0.14, -s*0.18, s*0.14],
          [ s*0.14, -s*0.18, s*0.14],
        ];
        pops.forEach(([px,py,pr]) => {
          ctx.beginPath();
          ctx.arc(px, py, pr, 0, Math.PI*2);
          ctx.stroke();
        });
        break;
      }

    
      case 'clapper': {
       
        ctx.beginPath();
        ctx.roundRect(-s*0.42, -s*0.12, s*0.84, s*0.56, s*0.06);
        ctx.stroke();
       
        ctx.beginPath();
        ctx.roundRect(-s*0.42, -s*0.42, s*0.84, s*0.3, [s*0.06, s*0.06, 0, 0]);
        ctx.stroke();
       
        for(let i = 0; i < 4; i++){
          const startX = -s*0.42 + i * s*0.22;
          ctx.beginPath();
          ctx.moveTo(startX, -s*0.42);
          ctx.lineTo(startX + s*0.14, -s*0.12);
          ctx.stroke();
        }
        
        ctx.lineWidth = size * 0.04;
        [-s*0.05, s*0.1, s*0.25].forEach(yy => {
          ctx.beginPath();
          ctx.moveTo(-s*0.32, yy); ctx.lineTo(s*0.32, yy);
          ctx.stroke();
        });
        break;
      }

    
      case 'camera': {
        ctx.beginPath();
        ctx.roundRect(-s*0.42, -s*0.28, s*0.64, s*0.56, s*0.06);
        ctx.stroke();
      
        ctx.beginPath();
        ctx.arc(-s*0.1, 0, s*0.2, 0, Math.PI*2);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(-s*0.1, 0, s*0.1, 0, Math.PI*2);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(s*0.22, -s*0.1);
        ctx.lineTo(s*0.42, -s*0.24);
        ctx.lineTo(s*0.42,  s*0.24);
        ctx.lineTo(s*0.22,  s*0.1);
        ctx.closePath();
        ctx.stroke();
        break;
      }

      
      case 'star': {
        const spikes = 5, outerR = s*0.42, innerR = s*0.18;
        ctx.beginPath();
        for(let i = 0; i < spikes*2; i++){
          const r = i%2===0 ? outerR : innerR;
          const a = (i * Math.PI / spikes) - Math.PI/2;
          i===0 ? ctx.moveTo(Math.cos(a)*r, Math.sin(a)*r)
                : ctx.lineTo(Math.cos(a)*r, Math.sin(a)*r);
        }
        ctx.closePath();
        ctx.stroke();
        break;
      }

      case 'film': {
        ctx.beginPath();
        ctx.roundRect(-s*0.48, -s*0.28, s*0.96, s*0.56, s*0.04);
        ctx.stroke();
        
        [-s*0.17, s*0.0, s*0.17].forEach(yy => {
          ctx.beginPath();
          ctx.roundRect(-s*0.44, yy - s*0.08, s*0.1, s*0.14, s*0.02);
          ctx.stroke();
        });
        [-s*0.17, s*0.0, s*0.17].forEach(yy => {
          ctx.beginPath();
          ctx.roundRect(s*0.34, yy - s*0.08, s*0.1, s*0.14, s*0.02);
          ctx.stroke();
        });
        [-s*0.18, s*0.04].forEach(xx => {
          ctx.beginPath();
          ctx.roundRect(xx - s*0.03, -s*0.18, s*0.22, s*0.36, s*0.02);
          ctx.stroke();
        });
        break;
      }
      case 'ticket': {
        ctx.beginPath();
        ctx.roundRect(-s*0.46, -s*0.24, s*0.92, s*0.48, s*0.06);
        ctx.stroke();

        ctx.setLineDash([s*0.05, s*0.05]);
        ctx.beginPath();
        ctx.moveTo(s*0.1, -s*0.24);
        ctx.lineTo(s*0.1,  s*0.24);
        ctx.stroke();
        ctx.setLineDash([]);
        
        ctx.beginPath();
        ctx.arc(-s*0.46, 0, s*0.08, -Math.PI/2, Math.PI/2);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc( s*0.46, 0, s*0.08, Math.PI/2, -Math.PI/2);
        ctx.stroke();
        
        ctx.lineWidth = size*0.04;
        [[-s*0.08, -s*0.1], [-s*0.08, s*0.02], [-s*0.08, s*0.12]].forEach(([lx,ly]) => {
          ctx.beginPath();
          ctx.moveTo(-s*0.3, ly); ctx.lineTo(lx, ly);
          ctx.stroke();
        });
        break;
      }
    }

    ctx.restore();
  }

  
  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const CELL = 90;          
    const ANGLE = Math.PI/6;  
    const icons = ['popcorn','clapper','camera','star','film','ticket'];

    const cols = Math.ceil(canvas.width  / CELL) + 4;
    const rows = Math.ceil(canvas.height / CELL) + 4;

    for(let row = -2; row < rows; row++){
      for(let col = -2; col < cols; col++){
        const offX = (row % 2) * (CELL / 2);
        const x = col * CELL + offX - CELL;
        const y = row * CELL - CELL;

        
        const idx = ((row * 7 + col * 13) & 0xffff) % icons.length;
       
        const baseAngle = (row + col) % 2 === 0 ? ANGLE : -ANGLE;

        drawIcon(ctx, icons[idx], x, y, 32, baseAngle);
      }
    }
  }

  window.addEventListener('resize', resize);
  resize();
})();
