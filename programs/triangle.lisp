(def n 10)
(def new-line "
")
(def s new-line)
(def i 0 j 0 d 1)
(def row (quote (do
    (set! s (++ s "@"))
    (set! j (- j 1))
    (cond (> j 0) (eval row)))))
(def task (quote (do
    (cond (>= i n) (set! d (- 0 d)))
    (set! i (+ i d) j i)
    (cond (> i 0) (do (eval row)
                      (set! s (++ s new-line))
                      (eval task))
          (set! d 1)))))
(eval task)

s

