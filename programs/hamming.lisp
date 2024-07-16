(def i 1 i-max 100 n 0 d 0)
(def remove-d
  (quote (cond (= 0 (mod n d)) (do (set! n (/ n d))
                             (eval remove-d)))))
(def task
  (quote (cond (<= i i-max) (do (set! n i)
                          (set! d 2) (eval remove-d)
                          (set! d 3) (eval remove-d)
                          (set! d 5) (eval remove-d)
                          (cond (= n 1) (print i " "))
                          (set! i (+ 1 i))
                          (eval task))
         (set! i 1))))
(eval task)

