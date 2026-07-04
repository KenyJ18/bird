import React, { useState } from 'react';
import { Stack, TextField, Button, Grid } from '@mui/material';
import { useRouter } from 'next/router';

interface EnterProps {
    initialBudget?: number;
}

export const Enter: React.FC<EnterProps> = ({ initialBudget }) => {
    const [budget, setBudget] = useState<string>(initialBudget?.toString() || '');
    const router = useRouter();

    const handleNext = () => {
        const budgetValue = parseFloat(budget);
        if (!isNaN(budgetValue) && budgetValue > 0) {
            // 予算確認画面に遷移（budgetをクエリパラメータとして渡す）
            router.push({
                pathname: '/enter-check',
                query: { budget: budgetValue }
            });
        } else {
            alert('有効な予算を入力してください');
        }
    };

    const handleCancel = () => {
        setBudget('');
    };

    return (
        <div>
            <h1>予算入力画面</h1>
            <Stack direction="column" spacing={2}>
                <Grid container spacing={2}>
                    <Grid item xs={12}>
                        <TextField
                            label="予算" 
                            variant="outlined" 
                            fullWidth 
                            value={budget} 
                            onChange={(e) => setBudget(e.target.value)}
                            type="number"
                        />
                    </Grid>
                </Grid>
                <Grid container spacing={2}>
                    <Grid item xs={6}>
                        <Button variant="contained" color="primary" onClick={handleNext} fullWidth>
                            次へ
                        </Button>
                    </Grid>
                    <Grid item xs={6}>
                        <Button variant="contained" color="secondary" onClick={handleCancel} fullWidth>
                            キャンセル
                        </Button>
                    </Grid>
                </Grid>
            </Stack>
        </div>
    );
};

export default Enter;
